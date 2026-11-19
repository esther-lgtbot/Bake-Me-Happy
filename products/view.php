<?php
require '../config/db.php';
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /EL3/admin/orders.php');
    exit;
}
require '../includes/header.php';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT p.*, u.name as seller FROM products p JOIN users u ON p.seller_id=u.id WHERE p.id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if(!$p){ echo "<p>Product not found</p>"; require '../includes/footer.php'; exit; }
?>

<div class="row mt-4">
  <div class="col-md-6">
    <?php if ($p['image'] !== ''): ?><img src="<?= APP_BASE ?>/uploads/<?= rawurlencode($p['image']) ?>" alt="<?= h($p['name']) ?>" style="width:100%; max-height:400px; object-fit:cover; border-radius:6px;"><?php endif; ?>
  </div>
  <div class="col-md-6">
    <h2><?= htmlspecialchars($p['name']) ?></h2>
    <p class="text-muted"><?= htmlspecialchars($p['description']) ?></p>
    <h4 class="price">₱<?= number_format((float) $p['price'], 2) ?></h4>
    <p><?= (int) $p['stock'] > 0 ? (int) $p['stock'] . ' available' : 'Currently out of stock' ?></p>
    <small>by <?= htmlspecialchars($p['seller']) ?></small>
    <br><br>
    <?php if (in_array($_SESSION['role'] ?? '', ['seller', 'admin'], true)): ?>
    <div class="alert alert-info">Seller accounts are for managing products and viewing sales.</div>
    <?php elseif ((int) $p['stock'] > 0): ?>
    <form method="POST" action="/EL3/cart/add.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <button class="btn btn-primary w-100">Add to Cart</button>
    </form>
    <?php endif; ?>
    <a href="/EL3/products/index.php" class="btn btn-outline-secondary w-100 mt-2">Back to Products</a>
  </div>
</div>

<?php require '../includes/footer.php'; ?>