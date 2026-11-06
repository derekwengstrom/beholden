<?php
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/layout.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrf();$action=(string)($_POST['action']??'');$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 if($id && $id>0){
  $q=db()->prepare('SELECT quantity FROM products WHERE productId=?');$q->execute([$id]);$stock=$q->fetchColumn();$available=availableInventory(db(),[$id])[$id]??0;
  if($stock!==false){$cart=cart();$qty=max(0,min(10000,(int)($_POST['quantity']??1)));
   if($action==='add')$qty=min($available,($cart[$id]??0)+max(1,$qty));
   if($action==='update')$qty=min($available,$qty);
   if($action==='remove')$qty=0;
   if($action==='add'||$action==='update'||$action==='remove'){
    if($qty>0)$cart[$id]=$qty;else unset($cart[$id]);$_SESSION['rental_cart']=$cart;
   }
  }
 }
 if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode(['ok'=>true,'count'=>cartCount()]);exit;
 }
 header('Location: /cart.php');exit;
}
$cart=cart();$items=[];$total=0;
if($cart){$ids=array_keys($cart);$q=db()->prepare('SELECT * FROM products WHERE productId IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY name');$q->execute($ids);$items=$q->fetchAll();}
if (($_GET['modal'] ?? '') === '1') {
 header('Content-Type: text/html; charset=utf-8');
 header('Cache-Control: no-store');
 ?>
 <h2 id="cart-modal-title">Your rental cart</h2>
 <?php if (!$items): ?><p>Your cart is empty.</p><?php else: ?>
 <div class="cart-modal-items">
 <?php foreach ($items as $item): $id=(int)$item['productId'];$qty=(int)$cart[$id]; ?>
 <div class="cart-modal-item"><div><strong><?=e($item['name'])?></strong><div class="cart-modal-muted">Quantity: <?=$qty?> × $<?=number_format((float)$item['price'],2)?></div></div><strong>$<?=number_format($qty*(float)$item['price'],2)?></strong></div>
 <?php endforeach; ?>
 </div>
 <div class="cart-modal-total"><strong>Total rental amount</strong><strong>$<?=number_format(array_reduce($items,static fn($sum,$item)=>$sum+(float)$item['price']*(int)($cart[(int)$item['productId']]??0),0),2)?></strong></div>
 <?php endif; ?>
 <div class="cart-modal-actions"><button type="button" class="secondary cart-continue">Continue shopping</button><?php if ($items): ?><a class="button" href="/checkout.php">Checkout</a><?php endif; ?></div>
 <p class="cart-modal-muted">Rental dates are selected during checkout. <a href="/cart.php">Edit quantities in cart</a>.</p>
 <?php exit;
}
pageStart('Rental cart');?><h1>Your rental cart</h1><p>Prices shown are for one 14-day rental period. Your return date is exactly 14 calendar days after pickup.</p>
<?php if(!$items):?><p>Your cart is empty. <a href="/products.php">Browse rentals</a>.</p><?php else:?>
<div class="tablewrap"><table><thead><tr><th>Item</th><th>Rate</th><th>Quantity</th><th>Subtotal</th><th></th></tr></thead><tbody>
<?php foreach($items as $item):$id=(int)$item['productId'];$qty=(int)$cart[$id];$sub=$qty*(float)$item['price'];$total+=$sub;?><tr><td><?=e($item['name'])?></td><td>$<?=number_format((float)$item['price'],2)?></td><td><form method="post" class="cart-controls"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="update"><input aria-label="Quantity for <?=e($item['name'])?>" type="number" name="quantity" min="0" max="<?=availableInventory(db(),[$id])[$id]??0?>" value="<?=$qty?>"><button>Update</button></form></td><td>$<?=number_format($sub,2)?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="remove"><button class="secondary">Remove</button></form></td></tr><?php endforeach;?></tbody></table></div>
<h2>Total rental amount: $<?=number_format($total,2)?></h2><a class="button" href="/checkout.php">Continue to checkout</a>
<?php endif;pageEnd();
