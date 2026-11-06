<?php
require dirname(__DIR__,2).'/app/bootstrap.php';requireAdmin();require dirname(__DIR__,2).'/app/layout.php';
$filter=(string)($_GET['status']??'active');$allowed=['active','reserved','out','returned','cancelled','all'];if(!in_array($filter,$allowed,true))$filter='active';
$where=match($filter){'active'=>"WHERE status IN ('reserved','out')",'all'=>'',default=>'WHERE status='.db()->quote($filter)};
$q=db()->query("SELECT * FROM rental_orders $where ORDER BY CASE WHEN status='out' THEN 0 ELSE 1 END,pickup_date ASC,id DESC LIMIT 300");$orders=$q->fetchAll();
$items=[];if($orders){$ids=array_column($orders,'id');$q=db()->prepare('SELECT * FROM rental_order_items WHERE order_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id');$q->execute($ids);foreach($q->fetchAll() as $item)$items[$item['order_id']][]=$item;}
pageStart('Rental orders');?><div class="toolbar"><h1>Rental orders</h1></div>
<nav class="order-filters" aria-label="Filter orders"><?php foreach($allowed as $status):?><a class="<?= $filter===$status?'active':'' ?>" href="?status=<?=e($status)?>"><?=e(ucfirst($status))?></a><?php endforeach;?></nav>
<p class="hint">Reserved items block availability for overlapping dates. Checked-out items block availability until you mark them returned, including overdue rentals. Orders are not charged online.</p>
<?php if(!$orders):?><p>No orders in this view.</p><?php endif;?>
<div class="order-list"><?php foreach($orders as $o):$overdue=$o['status']==='out' && $o['return_date']<date('Y-m-d');?><article class="card order-card"><div class="toolbar"><h2><?=e($o['order_number'])?></h2><strong class="<?= $overdue?'error':'' ?>"><?=e(orderStatusLabel($o['status']))?><?= $overdue?' — OVERDUE':'' ?></strong></div>
<p><strong><?=e($o['customer_name'])?></strong> · <a href="mailto:<?=e($o['customer_email'])?>"><?=e($o['customer_email'])?></a> · <?=e($o['customer_phone'])?></p>
<p>Pickup: <strong><?=e($o['pickup_date'])?></strong> · Due back: <strong><?=e($o['return_date'])?></strong> · Total: <strong>$<?=number_format((float)$o['total'],2)?></strong></p>
<?php if($o['customer_notes']):?><p>Notes: <?=nl2br(e($o['customer_notes']))?></p><?php endif;?><ul><?php foreach($items[$o['id']]??[] as $item):?><li><?= (int)$item['quantity'] ?> × <?=e($item['product_name'])?> ($<?=number_format((float)$item['unit_price'],2)?> each)</li><?php endforeach;?></ul>
<?php if(in_array($o['status'],['reserved','out'],true)):?><form method="post" action="/admin/order-action.php" class="order-actions"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="id" value="<?=(int)$o['id']?>">
<?php if($o['status']==='reserved'):?><button name="action" value="checkout">Mark picked up / out</button><button class="secondary" name="action" value="cancel" onclick="return confirm('Cancel this reservation?')">Cancel</button><?php else:?><button name="action" value="return" onclick="return confirm('Confirm all items in this order were physically returned?')">Mark returned / checked in</button><?php endif;?></form><?php endif;?></article><?php endforeach;?></div>
<?php pageEnd();
