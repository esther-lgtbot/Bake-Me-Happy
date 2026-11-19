<?php require '../config/db.php'; require_role('seller'); $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0; $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?'); $stmt->execute([$id]); $prod = $stmt->fetch(); if (!$prod || (int) $prod['seller_id'] !== (int) $_SESSION['user_id']) { http_response_code(404); exit('Product not found.'); } $error = ''; if ($_SERVER['REQUEST_METHOD'] === 'POST') { verify_csrf(); $name = trim($_POST['name'] ?? ''); $description = trim($_POST['description'] ?? ''); $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT); $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]); if ($name === '' || $price === false || $price < 0 || $stock === false) { $error = 'Enter a product name, non-negative price, and valid stock quantity.'; } else { try { $image = save_uploaded_image('image'); if ($image !== null) { $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?') ->execute([$name, $description, $price, $stock, $image, $id]); } else { $pdo->prepare('UPDATE products SET name = ?, description = ?, price = ?, stock = ? WHERE id = ?') ->execute([$name, $description, $price, $stock, $id]); } header('Location: /EL3/seller/index.php'); exit; } catch (RuntimeException $exception) { $error = $exception->getMessage(); } } } require '../includes/header.php'; ?>

<style>
  .edit-wrap{ max-width:760px; margin:0 auto; }
  .edit-head{ background:#FFF6EE; border:1px solid #F8D8B8; border-radius:18px; padding:20px 22px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
  .edit-head h3{ font-weight:800; color:#3B2314; letter-spacing:-.02em; margin:0; font-size:1.5rem; }
  .edit-head p{ color:#7C6A5E; font-size:13.5px; margin:4px 0 0; }
  .btn-back{ border-radius:999px !important; font-weight:600; padding:8px 16px !important; color:#3B2314 !important; border-color:#E7D5C3 !important; background:#fff !important; }
  .edit-card{ background:#FFFBF7; border:1px solid #F8D8B8; border-radius:18px; padding:24px; margin-top:14px; }
  .form-label{ font-weight:700; color:#3B2314; font-size:13.5px; margin-bottom:6px; }
  .form-label small{ font-weight:400; color:#7C6A5E; }
  .form-control{ border-radius:12px !important; border-color:#E7D5C3 !important; padding:11px 14px !important; background:#fff !important; }
  .form-control:focus{ border-color:#C2410C !important; box-shadow:0 0 0 3px rgba(194,65,12,.12) !important; }
  textarea.form-control{ min-height:100px; resize:vertical; }
  .stock-box{ display:flex; align-items:center; gap:8px; }
  .stock-btn{ width:42px; height:46px; border-radius:12px !important; font-weight:800; font-size:18px; padding:0 !important; border-color:#E7D5C3 !important; background:#fff !important; color:#3B2314 !important; }
  .stock-btn:hover{ background:#FFF6EE !important; }
  .stock-input{ text-align:center; font-weight:800; }
  .img-preview-wrap{ display:flex; gap:14px; align-items:center; background:#FFF6EE; border:1px dashed #F8D8B8; border-radius:14px; padding:12px; }
  .img-preview{ width:96px; height:96px; object-fit:cover; border-radius:12px; background:#FDF3E7; flex-shrink:0; }
  .img-empty{ width:96px; height:96px; border-radius:12px; background:#FDF3E7; display:flex; align-items:center; justify-content:center; font-weight:800; color:#A05A2C; font-size:11px; text-align:center; flex-shrink:0; }
  .btn-update{ background:#C2410C !important; border-color:#C2410C !important; color:#fff !important; border-radius:999px !important; font-weight:700; padding:12px !important; }
  .btn-update:hover{ background:#9A3412 !important; }
  .btn-cancel{ border-radius:999px !important; font-weight:600; padding:11px !important; color:#3B2314 !important; border-color:#E7D5C3 !important; background:#fff !important; }
  .hint{ color:#7C6A5E; font-size:12.5px; margin-top:6px; }
</style>

<div class="container mt-4 edit-wrap" style="padding-top:15px;">

  <div class="edit-head">
    <div>
      <h3>Edit Cake</h3>
      <p>Updating <b style="color:#3B2314;"><?= htmlspecialchars($prod['name']) ?></b> &bull; ₱<?= number_format((float)$prod['price'],2) ?> &bull; <?= (int)$prod['stock'] ?> in stock</p>
    </div>
    <a href="/EL3/seller/index.php" class="btn btn-back btn-outline-secondary btn-sm">Back to Dashboard</a>
  </div>

  <?php if ($error !== ''): ?><div class="alert alert-danger mt-3" style="border-radius:12px;"><?= h($error) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="edit-card">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

    <div class="mb-3">
      <label class="form-label" for="edit-name">Cake Name</label>
      <input type="text" id="edit-name" name="name" value="<?= htmlspecialchars($prod['name']) ?>" class="form-control" placeholder="e.g. Ube Cake" required>
    </div>

    <div class="mb-3">
      <label class="form-label" for="edit-desc">Description</label>
      <textarea id="edit-desc" name="description" class="form-control" placeholder="Describe your cake..." required><?= htmlspecialchars($prod['description']) ?></textarea>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-6">
        <label class="form-label" for="edit-price">Price (PHP)</label>
        <input type="number" id="edit-price" min="0" step="0.01" name="price" value="<?= h($prod['price']) ?>" class="form-control" required>
      </div>
      <div class="col-6">
        <label class="form-label" for="edit-stock">Stock</label>
        <div class="stock-box">
          <button type="button" class="btn btn-outline-secondary stock-btn" onclick="document.getElementById('edit-stock').stepDown()">-</button>
          <input type="number" id="edit-stock" name="stock" value="<?= (int) $prod['stock'] ?>" min="0" class="form-control stock-input" required>
          <button type="button" class="btn btn-outline-secondary stock-btn" onclick="document.getElementById('edit-stock').stepUp()">+</button>
        </div>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label">Current Image <small>- leave file empty to keep this</small></label>
      <div class="img-preview-wrap">
        <?php if ($prod['image'] !== ''): ?>
          <img id="preview-img" src="/EL3/uploads/<?= rawurlencode($prod['image']) ?>" alt="Current product image" class="img-preview">
        <?php else: ?>
          <div id="preview-empty" class="img-empty">No Image<br>Yet</div>
          <img id="preview-img" src="" alt="" class="img-preview" style="display:none;">
        <?php endif; ?>
        <div class="flex-grow-1">
          <label class="form-label" for="edit-image" style="margin:0;">Change Image (optional)</label>
          <input type="file" id="edit-image" name="image" class="form-control mt-1" accept="image/jpeg,image/png,image/webp,image/gif">
          <div class="hint">JPG, PNG, WEBP or GIF only. New image will preview on the left.</div>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-update w-100">Update Cake</button>
    <a href="/EL3/seller/index.php" class="btn btn-cancel btn-outline-secondary w-100 mt-2" style="text-decoration:none; text-align:center;">Cancel</a>
  </form>
</div>

<script>
  document.getElementById('edit-image').addEventListener('change', function(){
    const f = this.files[0]; if(!f) return;
    const url = URL.createObjectURL(f);
    const img = document.getElementById('preview-img');
    const empty = document.getElementById('preview-empty');
    img.src = url; img.style.display='block';
    if(empty) empty.style.display='none';
  });
</script>

<?php require '../includes/footer.php'; ?>