<?php
require '../config/db.php';
require_role('seller');
$my = $pdo->prepare(
    'SELECT p.*, COALESCE(s.sold_quantity, 0) AS sold_quantity, '
    . 'COALESCE(s.total_sold, 0) AS total_sold FROM products p '
    . 'LEFT JOIN (SELECT oi.product_id, SUM(oi.qty) AS sold_quantity, '
    . 'SUM(oi.qty * oi.price) AS total_sold FROM order_items oi '
    . 'JOIN orders o ON o.id = oi.order_id WHERE o.status <> \'cancelled\' '
    . 'GROUP BY oi.product_id) s ON s.product_id = p.id '
    . 'WHERE p.seller_id = ? ORDER BY p.id DESC'
);
$my->execute([$_SESSION['user_id']]);
$products = $my->fetchAll();
$totalUnitsSold = array_sum(array_column($products, 'sold_quantity'));
$totalSales = array_sum(array_column($products, 'total_sold'));
$salesReportQuery = $pdo->prepare(
    'SELECT '
    . 'COALESCE(SUM(CASE WHEN o.created_at >= CURDATE() '
    . 'THEN oi.qty * oi.price ELSE 0 END), 0) AS daily_sales, '
    . 'COALESCE(SUM(CASE WHEN o.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) '
    . 'THEN oi.qty * oi.price ELSE 0 END), 0) AS weekly_sales, '
    . 'COALESCE(SUM(CASE WHEN o.created_at >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\') '
    . 'THEN oi.qty * oi.price ELSE 0 END), 0) AS monthly_sales '
    . 'FROM order_items oi JOIN orders o ON o.id = oi.order_id '
    . 'JOIN products p ON p.id = oi.product_id '
    . 'WHERE p.seller_id = ? AND o.status <> \'cancelled\''
);
$salesReportQuery->execute([$_SESSION['user_id']]);
$salesReport = $salesReportQuery->fetch();
$lowStockThreshold = 5;
$outOfStockProducts = array_values(array_filter(
    $products,
    static fn (array $product): bool => (int) $product['stock'] === 0
));
$lowStockProducts = array_values(array_filter(
    $products,
    static fn (array $product): bool => (int) $product['stock'] > 0
        && (int) $product['stock'] <= $lowStockThreshold
));
require '../includes/header.php';
?>
<div class="container mt-4">
<h2>Seller Dashboard - My Products</h2>
<div class="alert alert-info">Seller accounts can upload products and track sales.</div>
<?php if ($outOfStockProducts): ?>
  <div class="alert alert-danger" role="alert">
    <strong>Out of stock:</strong>
    <?= h(implode(', ', array_column($outOfStockProducts, 'name'))) ?>.
    Restock these products to make them available again.
  </div>
<?php endif; ?>
<?php if ($lowStockProducts): ?>
  <div class="alert alert-warning" role="alert">
    <strong>Low stock (<?= $lowStockThreshold ?> or fewer units):</strong>
    <?= h(implode(', ', array_column($lowStockProducts, 'name'))) ?>.
    Consider restocking soon.
  </div>
<?php endif; ?>
<a href="/EL3/products/create.php" class="btn btn-warning mb-3">+ Add New Cake</a>
<a href="/EL3/seller/orders.php" class="btn btn-secondary mb-3">View Customer Orders</a>

<div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card"><div class="card-body">
        <div class="text-muted">Units sold (excluding cancelled orders)</div>
        <h3 class="mb-0"><?= (int) $totalUnitsSold ?></h3>
      </div></div>
    </div>
    <div class="col-md-6">
      <div class="card"><div class="card-body">
        <div class="text-muted">Total sales</div>
        <h3 class="mb-0">₱<?= number_format((float) $totalSales, 2) ?></h3>
      </div></div>
    </div>
  </div>

  <h3 class="h5 mb-3">Sales report <small class="text-muted">(excluding cancelled orders)</small></h3>
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted">Today's income</div>
        <h3 class="mb-0">₱<?= number_format((float) $salesReport['daily_sales'], 2) ?></h3>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted">This week's income </div>
        <h3 class="mb-0">₱<?= number_format((float) $salesReport['weekly_sales'], 2) ?></h3>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted">This month's income</div>
        <h3 class="mb-0">₱<?= number_format((float) $salesReport['monthly_sales'], 2) ?></h3>
      </div></div>
    </div>
  </div>

<?php if (!$products): ?>
  <div class="alert alert-info">Wala ka pang paninda. Click Add New Cake.</div>
<?php else: ?>
<div class="row g-4">
<?php foreach ($products as $p): ?>
  <div class="col-12 col-md-6 col-xl-4">
    <article class="card h-100 overflow-hidden">
      <?php if ($p['image'] !== ''): ?>
        <img
          src="<?= APP_BASE ?>/uploads/<?= rawurlencode($p['image']) ?>"
          alt="<?= h($p['name']) ?>"
          class="card-img-top"
          style="height:240px; object-fit:cover;"
        >
      <?php else: ?>
        <div class="bg-light d-flex align-items-center justify-content-center text-muted" style="height:240px;">
          No product image
        </div>
      <?php endif; ?>
      <div class="card-body d-flex flex-column">
        <h3 class="h5 card-title"><?= h($p['name']) ?></h3>
        <p class="card-text text-muted"><?= nl2br(h($p['description'])) ?></p>
        <dl class="row mb-3">
          <dt class="col-7">Price</dt>
          <dd class="col-5 text-end">₱<?= number_format((float) $p['price'], 2) ?></dd>
          <dt class="col-7">Available stock</dt>
          <dd class="col-5 text-end">
            <?= (int) $p['stock'] ?>
            <?php if ((int) $p['stock'] === 0): ?>
              <span class="badge text-bg-danger">Out of stock</span>
            <?php elseif ((int) $p['stock'] <= $lowStockThreshold): ?>
              <span class="badge text-bg-warning">Low stock</span>
            <?php endif; ?>
          </dd>
          <dt class="col-7">Units sold</dt>
          <dd class="col-5 text-end"><?= (int) $p['sold_quantity'] ?></dd>
          <dt class="col-7">Total sales</dt>
          <dd class="col-5 text-end mb-0">₱<?= number_format((float) $p['total_sold'], 2) ?></dd>
        </dl>
        <div class="mt-auto d-flex gap-2">
          <a href="/EL3/products/edit.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
          <form method="POST" action="/EL3/products/delete.php" onsubmit="return confirm('Delete this product?')">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-sm btn-danger">Delete</button>
          </form>
        </div>
      </div>
    </article>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php require '../includes/footer.php'; ?>
