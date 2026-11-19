<?php
require '../config/db.php';
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /EL3/admin/orders.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$familyName = "CASE "
    . "WHEN RIGHT(p.name, CHAR_LENGTH(' - Roll Cake')) = ' - Roll Cake' "
    . "THEN LEFT(p.name, CHAR_LENGTH(p.name) - CHAR_LENGTH(' - Roll Cake')) "
    . "WHEN RIGHT(p.name, CHAR_LENGTH(' - Rectangular Cake')) = ' - Rectangular Cake' "
    . "THEN LEFT(p.name, CHAR_LENGTH(p.name) - CHAR_LENGTH(' - Rectangular Cake')) "
    . "ELSE p.name END";
$variantOrder = "CASE "
    . "WHEN RIGHT(p.name, CHAR_LENGTH(' - Roll Cake')) = ' - Roll Cake' THEN 1 "
    . "WHEN RIGHT(p.name, CHAR_LENGTH(' - Rectangular Cake')) = ' - Rectangular Cake' THEN 2 "
    . "ELSE 0 END";
$sql = 'SELECT p.*, u.name AS seller, ' . $familyName . ' AS family_name '
    . 'FROM products p JOIN users u ON u.id = p.seller_id';
$orderBy = ' ORDER BY family_name, p.seller_id, ' . $variantOrder . ', p.id';
if ($search !== '') {
    $stmt = $pdo->prepare(
        $sql . ' WHERE p.name LIKE ? OR p.description LIKE ? OR u.name LIKE ?' . $orderBy
    );
    $term = '%' . $search . '%';
    $stmt->execute([$term, $term, $term]);
} else {
    $stmt = $pdo->query($sql . $orderBy);
}
$products = $stmt->fetchAll();
$productGroups = [];
foreach ($products as $product) {
    $groupKey = (string) $product['seller_id'] . ':' . $product['family_name'];
    $productGroups[$groupKey][] = $product;
}
require '../includes/header.php';
?>

<style>
  .shop-hero{ background:#FFF6EE; border:1px solid #F8D8B8; border-radius:20px; padding:28px; }
  .shop-hero h1{ font-weight:800; letter-spacing:-.02em; color:#3B2314; margin:0; font-size:1.9rem; }
  .shop-hero p{ color:#7C6A5E; margin:6px 0 0; font-size:.95rem; }
  .search-wrap{ display:flex; gap:10px; margin-top:18px; }
  .search-wrap input{ border-radius:999px !important; padding:11px 18px !important; border:1px solid #F8D8B8 !important; background:#fff !important; }
  .btn-search{ background:#C2410C !important; border-color:#C2410C !important; color:#fff !important; border-radius:999px !important; padding:11px 22px !important; font-weight:700; }
  .btn-search:hover{ background:#9A3412 !important; }
  .btn-clear{ border-radius:999px !important; padding:11px 18px !important; font-weight:600; color:#3B2314 !important; border-color:#E7D5C3 !important; background:#fff !important; }

  .cake-card{ background:#FFFBF7; border:1px solid #F8D8B8; border-radius:18px; overflow:hidden; transition:transform .15s, box-shadow .15s; height:100%; display:flex; flex-direction:column; }
  .cake-card:hover{ transform:translateY(-4px); box-shadow:0 12px 24px rgba(59,35,20,.10); }
  .cake-img{ width:100%; height:210px; object-fit:cover; background:#FDF3E7; display:block; }
  .cake-img-empty{ height:210px; display:flex; align-items:center; justify-content:center; background:#FDF3E7; color:#A05A2C; font-weight:800; font-size:1.1rem; }
  .cake-body{ padding:16px; display:flex; flex-direction:column; flex:1; }
  .cake-name{ font-weight:800; color:#3B2314; font-size:1.05rem; margin:0 0 4px; line-height:1.3; }
  .cake-desc{ color:#7C6A5E; font-size:13.5px; line-height:1.5; margin:0 0 12px; min-height:40px; }
  .cake-meta{ display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
  .cake-price{ color:#C2410C; font-weight:800; font-size:1.15rem; }
  .badge-stock{ font-size:11px; font-weight:700; padding:5px 10px; border-radius:999px; }
  .badge-in{ background:#EAF7EC; color:#2E7D32; }
  .badge-low{ background:#FFF3E0; color:#9A3412; }
  .badge-out{ background:#F3E8E8; color:#9E2A2A; }
  .cake-seller{ font-size:12.5px; color:#7C6A5E; margin-bottom:14px; }
  .cake-seller b{ color:#3B2314; }
  .btn-add{ background:#C2410C !important; border-color:#C2410C !important; color:#fff !important; border-radius:999px !important; font-weight:700; padding:10px !important; }
  .btn-add:hover{ background:#9A3412 !important; }
  .btn-view{ border-radius:999px !important; font-weight:600; padding:8px !important; color:#3B2314 !important; border-color:#E7D5C3 !important; }
  .empty-box{ background:#FFFBF7; border:1px dashed #F8D8B8; border-radius:16px; padding:32px; text-align:center; color:#7C6A5E; }
</style>

<div class="container mt-4">

  <!-- HERO + SEARCH -->
  <div class="shop-hero">
    <h1>Fresh From The Oven</h1>
  
    <form method="GET" action="/EL3/products/index.php" class="search-wrap" role="search">
      <label class="visually-hidden" for="product-search">Search cakes</label>
      <input type="search" id="product-search" name="search" class="form-control flex-grow-1" placeholder="Search cakes, descriptions, or sellers..." value="<?= h($search) ?>">
      <button type="submit" class="btn btn-search">Search</button>
      <?php if ($search !== ''): ?>
        <a href="/EL3/products/index.php" class="btn btn-clear">Clear</a>
      <?php endif; ?>
    </form>

    <?php if ($search !== ''): ?>
      <p style="margin-top:12px; font-size:14px;">Showing results for <b style="color:#3B2314;">&ldquo;<?= h($search) ?>&rdquo;</b></p>
    <?php endif; ?>
  </div>

  <!-- RESULTS -->
  <div class="mt-4">
    <?php if ($search !== '' && !$products): ?>
      <div class="empty-box">
        <div style="font-weight:800; color:#3B2314; font-size:1.1rem;">No products found</div>
        <div style="margin-top:4px;">No results for &ldquo;<?= h($search) ?>&rdquo;. Try another search.</div>
        <a href="/EL3/products/index.php" class="btn btn-clear mt-3">Show All Cakes</a>
      </div>
    <?php elseif (!$products): ?>
      <div class="empty-box">No cakes available yet. Please check back later.</div>
    <?php else: ?>
      <?php foreach ($productGroups as $group): ?>
        <div class="row g-3 mb-4">
          <?php foreach ($group as $p): ?>
            <?php $stock = (int)$p['stock']; $isOut = $stock <= 0; $isLow = !$isOut && $stock <= 5; ?>
            <div class="col-6 col-md-4">
              <div class="cake-card">
                <?php if ($p['image'] !== ''): ?>
                  <img src="<?= APP_BASE ?>/uploads/<?= rawurlencode($p['image']) ?>" alt="<?= h($p['name']) ?>" class="cake-img">
                <?php else: ?>
                  <div class="cake-img-empty">Bake Me Happy</div>
                <?php endif; ?>

                <div class="cake-body">
                  <h5 class="cake-name"><?= h($p['name']) ?></h5>
                  <p class="cake-desc"><?= h(mb_strimwidth($p['description'], 0, 90, '...')) ?></p>

                  <div class="cake-meta">
                    <span class="cake-price">₱<?= number_format((float) $p['price'], 2) ?></span>
                    <?php if ($isOut): ?>
                      <span class="badge-stock badge-out">Out of stock</span>
                    <?php elseif ($isLow): ?>
                      <span class="badge-stock badge-low">Only <?= $stock ?> left</span>
                    <?php else: ?>
                      <span class="badge-stock badge-in">In stock</span>
                    <?php endif; ?>
                  </div>

                  <div class="cake-seller">By <b><?= h($p['seller']) ?></b> &bull; <?= $stock ?> in stock</div>

                  <div class="mt-auto d-flex flex-column gap-2">
                    <?php if (in_array($_SESSION['role'] ?? '', ['seller', 'admin'], true)): ?>
                      <div class="alert alert-info py-2 mb-0" style="font-size:13px; border-radius:12px;">Seller accounts are for managing products and viewing sales.</div>
                    <?php elseif (!$isOut): ?>
                      <form method="POST" action="/EL3/cart/add.php" class="m-0">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button class="btn btn-add w-100">Add to Cart</button>
                      </form>
                    <?php else: ?>
                      <button class="btn btn-secondary w-100" style="border-radius:999px; padding:10px;" disabled>Out of stock</button>
                    <?php endif; ?>
                    <a href="/EL3/products/view.php?id=<?= (int) $p['id'] ?>" class="btn btn-view btn-outline-secondary w-100">View Details</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
<?php require '../includes/footer.php'; ?>