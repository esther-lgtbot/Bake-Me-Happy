<?php
require '../config/db.php';
require_role('admin');

$products = $pdo->query(
    'SELECT p.id, p.name, p.price, p.stock, p.image, u.name AS seller_name '
    . 'FROM products p JOIN users u ON u.id = p.seller_id '
    . 'WHERE u.role = \'seller\' ORDER BY p.id DESC'
)->fetchAll();

require '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h2 class="mb-1">Seller Products</h2>
    <p class="text-muted mb-0">View products uploaded by sellers.</p>
  </div>
</div>

<?php if (!$products): ?>
  <div class="alert alert-info">Sellers have not uploaded any products yet.</div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($products as $product): ?>
      <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        <article class="card h-100 overflow-hidden">
          <?php if ($product['image'] !== ''): ?>
            <img
              src="<?= APP_BASE ?>/uploads/<?= rawurlencode($product['image']) ?>"
              alt="<?= h($product['name']) ?>"
              class="card-img-top"
              style="height:220px; object-fit:cover;"
            >
          <?php else: ?>
            <div class="bg-light d-flex align-items-center justify-content-center text-muted" style="height:220px;">
              No product image
            </div>
          <?php endif; ?>
          <div class="card-body">
            <h3 class="h5 card-title"><?= h($product['name']) ?></h3>
            <p class="text-muted mb-2">Seller: <?= h($product['seller_name']) ?></p>
            <p class="price mb-2">₱<?= number_format((float) $product['price'], 2) ?></p>
            <p class="mb-0">Stock: <?= (int) $product['stock'] ?></p>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>
