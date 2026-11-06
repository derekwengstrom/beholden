<?php
require dirname(__DIR__,2).'/app/bootstrap.php';requireAdmin();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}verifyCsrf();
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);$action=(string)($_POST['action']??'');
$transitions=['checkout'=>['reserved','out','checked_out_at'],'return'=>['out','returned','checked_in_at'],'cancel'=>['reserved','cancelled','cancelled_at']];
if(!$id||!isset($transitions[$action])){http_response_code(422);exit('Invalid action');}
[$from,$to,$timestamp]=$transitions[$action];$pdo=db();$pdo->beginTransaction();try{
 $q=$pdo->prepare('SELECT status FROM rental_orders WHERE id=? FOR UPDATE');$q->execute([$id]);
 if($q->fetchColumn()!==$from)throw new RuntimeException('Order status changed; refresh the list.');
 $q=$pdo->prepare("UPDATE rental_orders SET status=?, $timestamp=NOW() WHERE id=?");$q->execute([$to,$id]);$pdo->commit();
 header('Location: /admin/orders.php');exit;
}catch(Throwable $ex){$pdo->rollBack();http_response_code(409);exit(e($ex->getMessage()));}
