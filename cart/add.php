<?php
require '../config/db.php';

if (in_array($_SESSION['role'] ?? '', ['seller', 'admin'], true)) {
    header('Location: ' . (($_SESSION['role'] ?? '') === 'admin' ? '/EL3/admin/orders.php' : '/EL3/seller/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Use POST to add an item to the cart.');
}
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}
$inCart = (int) ($_SESSION['cart'][$id] ?? 0);
if ((int) $product['stock'] <= $inCart) {
    $_SESSION['cart_error'] = 'There is not enough stock for that quantity.';
} else {
    $_SESSION['cart'][$id] = $inCart + 1;
}

header('Location: index.php');
exit;
?>
