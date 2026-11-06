<?php
require dirname(__DIR__,2).'/app/bootstrap.php';requireAdmin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}verifyCsrf();
$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT) ?: 0;
$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));$price=(string)($_POST['price']??'');
$new=$_POST['new_photo_keys']??[];$existing=$_POST['existing_photo_ids']??[];$removed=$_POST['removed_photo_ids']??[];
if(!is_array($new)||!is_array($existing)||!is_array($removed)||count($new)>30||count($existing)>200||count($removed)>200||$name===''||mb_strlen($name)>200||$description===''||!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/',$price)||(float)$price>99999999.99){http_response_code(422);exit('Invalid product data');}
if(count($new)!==count(array_unique($new))||count($existing)!==count(array_unique($existing))){http_response_code(422);exit('Duplicate photos');}
foreach($new as $key){if(!is_string($key)||!preg_match('/^products\/[a-f0-9]{32}\.(jpg|png|webp)$/',$key)){http_response_code(422);exit('Invalid photo key');}}
foreach(array_merge($existing,$removed) as $photoId){if(!ctype_digit((string)$photoId)||(int)$photoId<1){http_response_code(422);exit('Invalid photo ID');}}
if(array_intersect($existing,$removed)){http_response_code(422);exit('Conflicting photo actions');}
try{
 foreach($new as $key){s3()->headObject(['Bucket'=>envv('AWS_S3_BUCKET'),'Key'=>$key]);}
 $pdo=db();$pdo->beginTransaction();
 if($id){$q=$pdo->prepare('SELECT productId FROM products WHERE productId=? FOR UPDATE');$q->execute([$id]);if(!$q->fetch())throw new RuntimeException('Product not found');
 $q=$pdo->prepare('SELECT id FROM product_photos WHERE product_id=? FOR UPDATE');$q->execute([$id]);$current=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
 $requested=array_map('intval',$existing);$toRemove=array_map('intval',$removed);
 if(count(array_diff(array_merge($requested,$toRemove),$current))>0||count(array_diff($current,array_merge($requested,$toRemove)))>0)throw new RuntimeException('Photo list changed. Refresh and try again');
 }else{if($existing||$removed)throw new RuntimeException('Invalid photos for new product');}
 if($id){$q=$pdo->prepare('UPDATE products SET name=?,description=?,price=? WHERE productId=?');$q->execute([$name,$description,$price,$id]);}
 else{$q=$pdo->prepare('INSERT INTO products (name,description,price) VALUES (?,?,?)');$q->execute([$name,$description,$price]);$id=(int)$pdo->lastInsertId();}
 if($removed){$marks=implode(',',array_fill(0,count($removed),'?'));$q=$pdo->prepare("DELETE FROM product_photos WHERE product_id=? AND id IN ($marks)");$q->execute(array_merge([$id],array_map('intval',$removed)));}
 $position=0;
 foreach($existing as $photoId){$q=$pdo->prepare('UPDATE product_photos SET sort_order=? WHERE product_id=? AND id=?');$q->execute([$position++,$id,(int)$photoId]);}
 foreach($new as $key){$q=$pdo->prepare('INSERT INTO product_photos (product_id,photo_key,sort_order) VALUES (?,?,?)');$q->execute([$id,$key,$position++]);}
 $q=$pdo->prepare('SELECT photo_key FROM product_photos WHERE product_id=? ORDER BY sort_order,id LIMIT 1');$q->execute([$id]);$primary=$q->fetchColumn() ?: null;
 $q=$pdo->prepare('UPDATE products SET photo_key=? WHERE productId=?');$q->execute([$primary,$id]);
 $pdo->commit();header('Location: /admin/index.php');exit;
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Product save failed: '.$e->getMessage());http_response_code(422);exit('Unable to save product. Check photo permissions, database migration, and server logs.');}
