<?php
function pageStart(string $title): void {
    $isAdmin = admin() && str_starts_with(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '', '/admin/');
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> · <?= e(envv('APP_NAME','Event Rentals')) ?></title><link rel="stylesheet" href="/assets/style.css"></head>
<body class="<?= $isAdmin ? 'admin-shell-page' : '' ?>">
<header><nav class="container site-nav" aria-label="Main navigation"><a class="brand" href="/"><?= e(envv('APP_NAME','Event Rentals')) ?></a><div class="nav-links"><a href="/">Home</a><a href="/products.php">Rentals</a><a href="/cart.php">Cart (<?= cartCount() ?>)</a><a href="/admin/index.php">Admin</a></div></nav></header>
<?php if ($isAdmin): ?>
<div class="admin-shell">
<aside class="admin-sidebar" aria-label="Admin sidebar">
  <div class="admin-sidebar-heading">Management</div>
  <nav class="admin-sidebar-nav" aria-label="CMS sections">
    <a href="/admin/index.php" class="<?= in_array($current, ['index.php','edit.php'], true) ? 'is-active' : '' ?>" <?= in_array($current, ['index.php','edit.php'], true) ? 'aria-current="page"' : '' ?>><span aria-hidden="true">▦</span> Products</a>
    <a href="/admin/orders.php" class="<?= $current === 'orders.php' ? 'is-active' : '' ?>" <?= $current === 'orders.php' ? 'aria-current="page"' : '' ?>><span aria-hidden="true">▤</span> Orders</a>
  </nav>
  <div class="admin-sidebar-bottom"><a href="/" class="admin-sidebar-back">View storefront ↗</a></div>
</aside>
<main class="admin-content" id="main-content">
<?php else: ?><main class="container" id="main-content"><?php endif; ?>
<?php }
function pageEnd(): void {
    $isAdmin = admin() && str_starts_with(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '', '/admin/');
    ?></main><?php if ($isAdmin): ?></div><?php endif; ?>
<footer class="container">© <?= date('Y') ?> <?= e(envv('APP_NAME','Event Rentals')) ?></footer></body></html><?php
}
