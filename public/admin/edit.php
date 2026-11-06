<?php
require dirname(__DIR__,2).'/app/bootstrap.php'; requireAdmin(); require dirname(__DIR__,2).'/app/layout.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT) ?: 0;
$p=['name'=>'','description'=>'','price'=>'','quantity'=>0,'photo_key'=>null]; $photos=[];
if($id){$q=db()->prepare('SELECT * FROM products WHERE productId=?');$q->execute([$id]);$p=$q->fetch();if(!$p){http_response_code(404);exit('Product not found');}$photos=photosForProducts([['productId'=>$id]])[$id]??[];}
pageStart($id?'Edit product':'Add product'); ?>
<div class="panel"><h1><?= $id?'Edit':'Add' ?> product</h1>
<form method="post" action="/admin/save.php" id="productForm">
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= $id ?>">
<label>Name<input name="name" required maxlength="200" value="<?= e($p['name']) ?>"></label>
<label>Description<textarea name="description" rows="5" required><?= e($p['description']) ?></textarea></label>
<label>Price (USD)<input type="number" name="price" min="0" max="99999999.99" step="0.01" required value="<?= e((string)$p['price']) ?>"></label>
<label>Quantity<input type="number" name="quantity" min="0" max="4294967295" step="1" required value="<?= (int)$p['quantity'] ?>"></label>
<label>Product photos (JPEG, PNG, WebP; max 5 MB each)<input type="file" id="photos" accept="image/jpeg,image/png,image/webp" multiple></label>
<div class="photo-admin-grid" id="newPreviews" aria-live="polite"></div><p class="hint">A 320px JPEG thumbnail is generated in your browser for each selected image and uploaded to S3 alongside the original. Choose several photos at once, or edit this product later to add more. First photo is the primary image.</p>
<?php if($photos): ?><h3>Existing photos</h3><div class="photo-admin-grid" id="existingPhotos">
<?php foreach($photos as $i=>$photo): $url=photoUrl($photo['thumbnail_key'] ?: $photo['photo_key']); ?>
<div class="photo-admin-item" data-photo-id="<?= (int)$photo['id'] ?>">
<?php if($url): ?><img src="<?= e($url) ?>" alt="Product photo <?= $i+1 ?>"><?php endif; ?>
<div class="photo-controls"><button type="button" class="secondary move-left" title="Move earlier">←</button><button type="button" class="secondary move-right" title="Move later">→</button><button type="button" class="danger remove-photo">Remove</button></div>
</div><?php endforeach; ?></div><?php endif; ?>
<p id="status" role="status" aria-live="polite"></p><button id="save">Save product</button> <a href="/admin/index.php">Cancel</a>
</form></div>
<script>
const form=document.getElementById('productForm'), input=document.getElementById('photos'), statusEl=document.getElementById('status'), saveButton=document.getElementById('save');
const grid=document.getElementById('existingPhotos');
const removed=new Set();
const previews=document.getElementById('newPreviews');
input.addEventListener('change',()=>{
 previews.replaceChildren();
 for(const file of input.files){
  const item=document.createElement('div');item.className='photo-admin-item';
  const img=document.createElement('img');img.alt='New photo preview';img.src=URL.createObjectURL(file);
  img.addEventListener('load',()=>URL.revokeObjectURL(img.src),{once:true});
  const label=document.createElement('small');label.textContent=file.name;
  item.append(img,label);previews.append(item);
 }
});
async function thumbnailBlob(file){
 const bitmap=await createImageBitmap(file);
 try{
  const ratio=Math.min(1,320/Math.max(bitmap.width,bitmap.height));
  const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bitmap.width*ratio));canvas.height=Math.max(1,Math.round(bitmap.height*ratio));
  const ctx=canvas.getContext('2d');if(!ctx)throw Error('Canvas unavailable');
  ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);
  return await new Promise((resolve,reject)=>canvas.toBlob(b=>b?resolve(b):reject(Error('Could not generate thumbnail')),'image/jpeg',0.82));
 }finally{bitmap.close();}
}
async function uploadFile(blob,type,kind,csrfToken){
 const body=new FormData();body.append('csrf',csrfToken);body.append('content_type',type);body.append('size',String(blob.size));body.append('kind',kind);
 const response=await fetch('/admin/presign.php',{method:'POST',body,credentials:'same-origin'});
 const data=await response.json();if(!response.ok)throw Error(data.error||'Unable to prepare upload');
 const upload=await fetch(data.url,{method:'PUT',headers:{'Content-Type':type},body:blob});
 if(!upload.ok)throw Error('S3 upload failed. Check CORS and S3 permissions.');return data.key;
}
if(grid){grid.addEventListener('click',e=>{const item=e.target.closest('.photo-admin-item');if(!item)return;
if(e.target.closest('.remove-photo')){removed.add(item.dataset.photoId);item.remove();return;}
if(e.target.closest('.move-left') && item.previousElementSibling)grid.insertBefore(item,item.previousElementSibling);
if(e.target.closest('.move-right') && item.nextElementSibling)grid.insertBefore(item.nextElementSibling,item);
});}
form.addEventListener('submit',async event=>{
 event.preventDefault();if(!form.reportValidity())return;saveButton.disabled=true;
 try{
  const keys=[],thumbKeys=[];const files=[...input.files];
  if(files.length>30)throw Error('Upload at most 30 images per save');
  for(let i=0;i<files.length;i++){
   const file=files[i];if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size===0||file.size>5*1024*1024)throw Error('Each photo must be JPEG, PNG or WebP and no larger than 5 MB');
   statusEl.textContent=`Generating thumbnail ${i+1} of ${files.length}…`;
   const thumb=await thumbnailBlob(file);
   statusEl.textContent=`Uploading original and thumbnail ${i+1} of ${files.length}…`;
   keys.push(await uploadFile(file,file.type,'original',form.elements.csrf.value));
   thumbKeys.push(await uploadFile(thumb,'image/jpeg','thumbnail',form.elements.csrf.value));
  }
  const payload=new FormData(form);
  payload.delete('new_photo_keys[]');payload.delete('new_thumbnail_keys[]');payload.delete('existing_photo_ids[]');payload.delete('removed_photo_ids[]');
  keys.forEach(key=>payload.append('new_photo_keys[]',key));thumbKeys.forEach(key=>payload.append('new_thumbnail_keys[]',key));
  if(grid)grid.querySelectorAll('.photo-admin-item').forEach(item=>payload.append('existing_photo_ids[]',item.dataset.photoId));
  removed.forEach(id=>payload.append('removed_photo_ids[]',id));
  statusEl.textContent='Saving product…';
  const response=await fetch('/admin/save.php',{method:'POST',body:payload,credentials:'same-origin',redirect:'follow'});
  if(!response.ok)throw Error((await response.text()).slice(0,300)||'Unable to save product');
  window.location.href='/admin/index.php';
 }catch(error){statusEl.textContent=error.message;saveButton.disabled=false;}
});
</script><?php pageEnd();
