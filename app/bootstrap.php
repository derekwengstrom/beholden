<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
session_start();
function envv(string $key, string $default=''): string { return $_ENV[$key] ?? $_SERVER[$key] ?? $default; }
function db(): PDO { static $pdo; if (!$pdo) {
 $pdo = new PDO('mysql:host='.envv('DB_HOST').';port='.envv('DB_PORT','3306').';dbname='.envv('DB_DATABASE').';charset=utf8mb4',envv('DB_USERNAME'),envv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 } return $pdo; }
function s3(): Aws\S3\S3Client { static $client; return $client ??= new Aws\S3\S3Client(['version'=>'latest','region'=>envv('AWS_REGION')]); }
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verifyCsrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Invalid CSRF token'); } }
function admin(): bool { return isset($_SESSION['admin_id']); }
function requireAdmin(): void { if (!admin()) { header('Location: /admin/login.php'); exit; } }
function photoUrl(?string $key): ?string { if (!$key) return null; try { $cmd=s3()->getCommand('GetObject',['Bucket'=>envv('AWS_S3_BUCKET'),'Key'=>$key]); return (string)s3()->createPresignedRequest($cmd,'+15 minutes')->getUri(); } catch (Throwable $e) { error_log($e->getMessage()); return null; } }
function productPhotoKey(int $id): ?string { $q=db()->prepare('SELECT photo_key FROM products WHERE productId=?'); $q->execute([$id]); $r=$q->fetch(); return $r ? $r['photo_key'] : null; }

function photosForProducts(array $products): array {
 if (!$products) return [];
 $ids=array_map(static fn($p)=>(int)$p['productId'],$products);
 $marks=implode(',',array_fill(0,count($ids),'?'));
 $q=db()->prepare("SELECT id,product_id,photo_key,thumbnail_key,sort_order FROM product_photos WHERE product_id IN ($marks) ORDER BY sort_order,id");
 $q->execute($ids); $result=[];
 foreach($q->fetchAll() as $photo) $result[(int)$photo['product_id']][]=$photo;
 return $result;
}
function galleryForProduct(array $product, array $photosByProduct): array {
 $photos=$photosByProduct[(int)$product['productId']] ?? [];
 return array_map(static fn($photo)=>['id'=>(int)$photo['id'],'url'=>photoUrl($photo['photo_key']),'thumbnail_url'=>photoUrl($photo['thumbnail_key'] ?: $photo['photo_key'])],$photos);
}

function cart(): array { return $_SESSION['rental_cart'] ?? []; }
function cartCount(): int { return array_sum(array_map('intval',cart())); }
function rentalDates(string $pickup, string $return): bool {
 $a=DateTimeImmutable::createFromFormat('!Y-m-d',$pickup);
 $b=DateTimeImmutable::createFromFormat('!Y-m-d',$return);
 $today=new DateTimeImmutable('today');
 return $a && $b && $a->format('Y-m-d')===$pickup && $b->format('Y-m-d')===$return
     && $a >= $today && $a->modify('+14 days')->format('Y-m-d')===$return;
}
function availabilityFor(PDO $pdo, int $productId, string $pickup, string $return): int {
 $q=$pdo->prepare("SELECT p.quantity - COALESCE((SELECT SUM(oi.quantity)
   FROM rental_order_items oi JOIN rental_orders o ON o.id=oi.order_id
   WHERE oi.product_id=p.productId AND
   (o.status='out' OR (o.status='reserved' AND o.pickup_date < :return_date AND o.return_date > :pickup_date))),0)
   FROM products p WHERE p.productId=:id");
 $q->execute([':return_date'=>$return,':pickup_date'=>$pickup,':id'=>$productId]);
 $result=$q->fetchColumn();return $result===false ? 0 : max(0,(int)$result);
}
function orderStatusLabel(string $status): string { return ['reserved'=>'Reserved / awaiting pickup','out'=>'Checked out','returned'=>'Returned','cancelled'=>'Cancelled'][$status] ?? $status; }
