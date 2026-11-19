<?php
require '../config/db.php';
if (in_array($_SESSION['role'] ?? '', ['seller', 'admin'], true)) {
  header('Location: ' . (($_SESSION['role'] ?? '') === 'admin' ? '/EL3/admin/orders.php' : '/EL3/seller/index.php'));
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Use POST to update the cart.');
}
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$action = $_POST['action'] ?? '';
if ($id > 0 && isset($_SESSION['cart'][$id])) {
  if ($action === 'plus') {
    $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $stmt->execute([$id]);
    if ($_SESSION['cart'][$id] < (int) $stmt->fetchColumn()) {
      $_SESSION['cart'][$id]++;
    }
  } elseif ($action === 'minus') {
    $_SESSION['cart'][$id]--;
    if ($_SESSION['cart'][$id] <= 0) {
      unset($_SESSION['cart'][$id]);
    }
  } elseif ($action === 'remove') {
    unset($_SESSION['cart'][$id]);
  }
}
header('Location: /EL3/cart/index.php');
exit;
?>
