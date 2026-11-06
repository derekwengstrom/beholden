<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireAdmin();
require dirname(__DIR__, 2) . '/app/layout.php';

$perPage = 40;
$search = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($search, 'UTF-8') > 200) $search = mb_substr($search, 0, 200, 'UTF-8');
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

// Match all product fields, including numeric ID, price, quantity and dates,
// plus the original and thumbnail S3 keys in product_photos.
$where = '';
$params = [];
if ($search !== '') {
    $where = " WHERE (CAST(p.productId AS CHAR) LIKE :s1
        OR p.name LIKE :s2 OR p.description LIKE :s3
        OR p.size LIKE :s11 OR p.color LIKE :s12 OR p.material LIKE :s13
        OR CAST(p.price AS CHAR) LIKE :s4
        OR CAST(p.quantity AS CHAR) LIKE :s5
        OR COALESCE(p.photo_key, '') LIKE :s6
        OR CAST(p.created_at AS CHAR) LIKE :s7
        OR CAST(p.updated_at AS CHAR) LIKE :s8
        OR EXISTS (SELECT 1 FROM product_photos ph
                   WHERE ph.product_id = p.productId
                   AND (ph.photo_key LIKE :s9 OR COALESCE(ph.thumbnail_key, '') LIKE :s10)))";
    $params = array_combine(array_map(static fn($n) => ':s'.$n, range(1, 13)), array_fill(0, 13, '%'.$search.'%'));
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM products p' . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare('SELECT p.* FROM products p' . $where . ' ORDER BY p.productId DESC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) $stmt->bindValue($key, $value, PDO::PARAM_STR);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();
$photosByProduct = photosForProducts($products);
$available = availableInventory(db(),array_column($products,'productId'));
function adminPageUrl(int $number, string $search): string {
    return '/admin/index.php?' . http_build_query(array_filter(['page' => $number, 'q' => $search], static fn($v) => $v !== ''));
}
pageStart('Manage products');
?>
<div class="toolbar">
    <h1>Manage products</h1>
    <a class="button" href="/admin/edit.php">Add product</a>
</div>
<form class="admin-search" action="/admin/index.php" method="get" role="search">
    <label for="product-search">Search products</label>
    <input id="product-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Name, size, color, material, description, ID, price…" maxlength="200">
    <button type="submit">Search</button>
    <?php if ($search !== ''): ?><a href="/admin/index.php">Clear</a><?php endif; ?>
</form>
<p class="admin-results">
    <?php if ($total): ?>Showing <?= number_format($offset + 1) ?>–<?= number_format($offset + count($products)) ?> of <?= number_format($total) ?> products<?php else: ?>No matching products<?php endif; ?>
    <?php if ($search !== ''): ?> for “<?= e($search) ?>”<?php endif; ?>
</p>
<div class="tablewrap admin-tablewrap"><table class="admin-products-table">
<thead><tr><th>ID</th><th>Preview</th><th>Product</th><th>Description</th><th>Size</th><th>Color</th><th>Material</th><th>Price</th><th>Quantity</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($products as $p): ?>
<tr>
<td data-label="ID"><?= (int)$p['productId'] ?></td>
<td data-label="Preview"><?php $first = $photosByProduct[(int)$p['productId']][0] ?? null; $preview = photoUrl($first ? ($first['thumbnail_key'] ?: $first['photo_key']) : $p['photo_key']); if ($preview): ?><img class="admin-list-thumb" src="<?= e($preview) ?>" alt=""><?php endif; ?></td>
<td data-label="Product"><?= e($p['name']) ?></td>
<td data-label="Description" class="admin-description"><?= nl2br(e($p['description'])) ?></td>
<td data-label="Size"><?= e($p['size']) ?></td>
<td data-label="Color"><?= e($p['color']) ?></td>
<td data-label="Material"><?= e($p['material']) ?></td>
<td data-label="Price">$<?= number_format((float)$p['price'], 2) ?></td>
<td data-label="Quantity"><?= number_format($available[(int)$p['productId']]??0) ?> available / <?= number_format((int)$p['quantity']) ?> owned</td>
<td data-label="Actions" class="admin-actions"><a href="/admin/edit.php?id=<?= (int)$p['productId'] ?>">Edit</a> <form class="inline" method="post" action="/admin/delete.php" onsubmit="return confirm('Delete this product?')"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$p['productId'] ?>"><button class="danger">Delete</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($totalPages > 1): ?>
<nav class="admin-pagination" aria-label="Product pages">
    <?php if ($page > 1): ?><a href="<?= e(adminPageUrl($page - 1, $search)) ?>">← Previous</a><?php endif; ?>
    <?php for ($n = max(1, $page - 2); $n <= min($totalPages, $page + 2); $n++): ?>
        <?php if ($n === $page): ?><span class="current" aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e(adminPageUrl($n, $search)) ?>"><?= $n ?></a><?php endif; ?>
    <?php endfor; ?>
    <span>Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?><a href="<?= e(adminPageUrl($page + 1, $search)) ?>">Next →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php pageEnd(); ?>
