<?php
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/layout.php';
$cart=cart();if(!$cart){header('Location: /cart.php');exit;}
$error='';$pickup=(string)($_POST['pickup_date']??'');$return=(string)($_POST['return_date']??'');
if($_SERVER['REQUEST_METHOD']==='POST'){
 verifyCsrf();$name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));$phone=trim((string)($_POST['phone']??''));$notes=trim((string)($_POST['notes']??''));
 if(!rentalDates($pickup,$return))$error='Pickup must be today or later, and return must be exactly 14 days after pickup.';
 elseif(!$name||mb_strlen($name)>200||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>254||!$phone||mb_strlen($phone)>50||mb_strlen($notes)>3000)$error='Please enter valid contact information.';
 else{
  $pdo=db();try{
   $pdo->beginTransaction();$ids=array_keys($cart);sort($ids,SORT_NUMERIC);
   $q=$pdo->prepare('SELECT productId,name,price,quantity FROM products WHERE productId IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY productId FOR UPDATE');$q->execute($ids);$products=$q->fetchAll();
   if(count($products)!==count($ids))throw new RuntimeException('One of your products is no longer available.');
   $total=0;foreach($products as $p){$id=(int)$p['productId'];$qty=(int)$cart[$id];if($qty<1||$qty>10000||availabilityFor($pdo,$id,$pickup,$return)<$qty)throw new RuntimeException($p['name'].' does not have enough availability for those dates.');$total+=(float)$p['price']*$qty;}
   $number='R'.strtoupper(bin2hex(random_bytes(7)));
   $q=$pdo->prepare('INSERT INTO rental_orders (order_number,customer_name,customer_email,customer_phone,customer_notes,pickup_date,return_date,total) VALUES (?,?,?,?,?,?,?,?)');
   $q->execute([$number,$name,$email,$phone,$notes,$pickup,$return,number_format($total,2,'.','')]);$orderId=(int)$pdo->lastInsertId();
   $q=$pdo->prepare('INSERT INTO rental_order_items (order_id,product_id,product_name,quantity,unit_price) VALUES (?,?,?,?,?)');
   foreach($products as $p)$q->execute([$orderId,$p['productId'],$p['name'],$cart[(int)$p['productId']],$p['price']]);
   $pdo->commit();unset($_SESSION['rental_cart']);$_SESSION['completed_order']=$number;header('Location: /order-success.php');exit;
  }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$error=$ex instanceof RuntimeException?$ex->getMessage():'Unable to place reservation. Please try again.';error_log('Rental checkout: '.$ex->getMessage());}
 }
}
pageStart('Checkout');?><h1>Rental checkout</h1><p>Choose a pickup date. Your return date is automatically set to 14 days later. Inventory availability is checked when you place your reservation.</p>
<?php if($error):?><p class="error" role="alert"><?=e($error)?></p><?php endif;?>
<div class="panel"><form method="post" id="checkout-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>">
<label>Full name<input required maxlength="200" name="name" autocomplete="name" value="<?=e($_POST['name']??'')?>"></label>
<label>Email<input required type="email" maxlength="254" name="email" autocomplete="email" value="<?=e($_POST['email']??'')?>"></label>
<label>Phone<input required type="tel" maxlength="50" name="phone" autocomplete="tel" value="<?=e($_POST['phone']??'')?>"></label>
<label>Pickup date<input required type="date" id="pickup" name="pickup_date" min="<?=date('Y-m-d')?>" value="<?=e($pickup)?>"></label>
<label>Return date (14 days after pickup)<input required readonly type="date" id="return" name="return_date" value="<?=e($return)?>"></label>
<label>Notes (optional)<textarea name="notes" rows="3" maxlength="3000"><?=e($_POST['notes']??'')?></textarea></label>
<p class="hint">This checkout creates a rental reservation. No online payment is collected; arrange payment and pickup with the rental business.</p>
<button type="submit">Place rental reservation</button></form></div>
<script>const pickup=document.getElementById('pickup'),ret=document.getElementById('return');pickup.addEventListener('change',()=>{if(!pickup.value){ret.value='';return;}const d=new Date(pickup.value+'T12:00:00');d.setDate(d.getDate()+14);ret.value=[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');});</script>
<?php pageEnd();
