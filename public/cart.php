<?php
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/layout.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrf();$action=(string)($_POST['action']??'');$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 if($id && $id>0){
  $q=db()->prepare('SELECT quantity FROM products WHERE productId=?');$q->execute([$id]);$stock=$q->fetchColumn();
  if($stock!==false){$cart=cart();$qty=max(0,min(10000,(int)($_POST['quantity']??1)));
   if($action==='add')$qty=min((int)$stock,($cart[$id]??0)+max(1,$qty));
   if($action==='remove')$qty=0;
   if($action==='add'||$action==='update'||$action==='remove'){
    if($qty>0)$cart[$id]=$qty;else unset($cart[$id]);$_SESSION['rental_cart']=$cart;
   }
  }
 }
 header('Location: /cart.php');exit;
}
$cart=cart();$items=[];$total=0;
if($cart){$ids=array_keys($cart);$q=db()->prepare('SELECT * FROM products WHERE productId IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY name');$q->execute($ids);$items=$q->fetchAll();}
pageStart('Rental cart');?><h1>Your rental cart</h1><p>Prices shown are for one 14-day rental period. Your return date is exactly 14 calendar days after pickup.</p>
<?php if(!$items):?><p>Your cart is empty. <a href="/products.php">Browse rentals</a>.</p><?php else:?>
<div class="tablewrap"><table><thead><tr><th>Item</th><th>Rate</th><th>Quantity</th><th>Subtotal</th><th></th></tr></thead><tbody>
<?php foreach($items as $item):$id=(int)$item['productId'];$qty=(int)$cart[$id];$sub=$qty*(float)$item['price'];$total+=$sub;?><tr><td><?=e($item['name'])?></td><td>$<?=number_format((float)$item['price'],2)?></td><td><form method="post" class="cart-controls"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="update"><input aria-label="Quantity for <?=e($item['name'])?>" type="number" name="quantity" min="0" max="<?=(int)$item['quantity']?>" value="<?=$qty?>"><button>Update</button></form></td><td>$<?=number_format($sub,2)?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="remove"><button class="secondary">Remove</button></form></td></tr><?php endforeach;?></tbody></table></div>
<h2>Total rental amount: $<?=number_format($total,2)?></h2><a class="button" href="/checkout.php">Continue to checkout</a>
<?php endif;pageEnd();
