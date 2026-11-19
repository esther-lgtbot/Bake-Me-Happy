<?php
require '../config/db.php';
require_role('seller');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($name === '' || $price === false || $price < 0 || $stock === false) {
        $error = 'Enter a product name, non-negative price, and valid stock quantity.';
    } else {
        try {
            $image = save_uploaded_image('image') ?? '';
            $stmt = $pdo->prepare('INSERT INTO products (name, price, stock, description, image, seller_id) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $price, $stock, $description, $image, $_SESSION['user_id']]);
            header('Location: ../seller/index.php');
            exit;
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}
require '../includes/header.php';
?>

<div class="container mt-4" style="max-width:600px;">
<h2>Add Cake - Bake Me Happy</h2>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <label>Cake Name</label>
    <input type="text" name="name" class="form-control" placeholder="Ex: Choco Moist Cake" required><br>
    
    <label>Price</label>
    <input type="number" name="price" min="0" step="0.01" class="form-control" placeholder="Ex: 550" required><br>

    <label>Stock</label>
    <input type="number" name="stock" min="0" value="0" class="form-control" required><br>
    
    <label>Description</label>
    <textarea name="description" class="form-control" rows="3"></textarea><br>
    
    <label>Cake Photo</label>
    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif"><br>
    
    <button class="btn btn-warning">Add Cake</button>
</form>
</div>

<?php require '../includes/footer.php'; ?>
