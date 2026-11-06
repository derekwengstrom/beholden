<?php
function pageStart(string $title): void { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> · <?= e(envv('APP_NAME','Event Rentals')) ?></title><link rel="stylesheet" href="/assets/style.css"></head><body><header><nav class="container site-nav" aria-label="Main navigation"><a class="brand" href="/"><?= e(envv('APP_NAME','Event Rentals')) ?></a><div class="nav-links"><a href="/">Home</a><a href="/products.php">Rentals</a><a href="/cart.php">Cart (<?= cartCount() ?>)</a><a href="/admin/index.php">Admin</a></div></nav></header><main class="container">
<?php }
function pageEnd(): void { ?></main><footer class="container">© <?= date('Y') ?> <?= e(envv('APP_NAME','Event Rentals')) ?></footer></body></html><?php }
