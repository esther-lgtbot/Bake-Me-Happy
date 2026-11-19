<?php
require '../config/db.php';
require_login();
if (in_array($_SESSION['role'] ?? '', ['seller', 'admin'], true)) {
    header('Location: ' . (($_SESSION['role'] ?? '') === 'admin' ? '/EL3/admin/orders.php' : '/EL3/seller/index.php'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
verify_csrf();
if (empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

$orderId = 0;
$total = 0.0;
$error = '';
try {
    $customerPhone = $_POST['customer_phone'] ?? '';
    $fulfillmentMethod = $_POST['fulfillment_method'] ?? '';
    $deliveryAddress = $_POST['delivery_address'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    if (!is_string($customerPhone) || !is_string($fulfillmentMethod)
        || !is_string($deliveryAddress) || !is_string($paymentMethod)) {
        throw new RuntimeException('Please enter valid contact and delivery details.');
    }

    $customerPhone = trim($customerPhone);
    $deliveryAddress = trim($deliveryAddress);
    $_SESSION['checkout_details'] = [
        'customer_phone' => $customerPhone,
        'fulfillment_method' => $fulfillmentMethod,
        'delivery_address' => $deliveryAddress,
        'payment_method' => $paymentMethod,
    ];

    if (preg_match('/\A[0-9+().\-\s]{7,30}\z/D', $customerPhone) !== 1
        || preg_match_all('/[0-9]/', $customerPhone) < 7) {
        throw new RuntimeException('Enter a valid phone number so the seller can contact you.');
    }
    if (!in_array($fulfillmentMethod, ['pickup', 'delivery'], true)) {
        throw new RuntimeException('Choose pickup or delivery for your order.');
    }
    if ($fulfillmentMethod === 'delivery' && ($deliveryAddress === '' || strlen($deliveryAddress) > 1000)) {
        throw new RuntimeException('Enter a delivery address of 1,000 characters or fewer.');
    }

    $allowedPaymentMethods = $fulfillmentMethod === 'pickup'
        ? ['gcash', 'bank_transfer', 'pay_in_store']
        : ['gcash', 'bank_transfer', 'cod'];
    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        throw new RuntimeException(
            $fulfillmentMethod === 'pickup'
                ? 'Choose GCash, bank transfer, or payment in the store for pickup.'
                : 'Choose GCash, bank transfer, or cash on delivery for delivery.'
        );
    }

    $submittedNotes = $_POST['notes'] ?? [];
    if (!is_array($submittedNotes)) {
        throw new RuntimeException('Please enter a valid customization request.');
    }
    $customNotes = [];
    foreach ($_SESSION['cart'] as $id => $quantity) {
        $note = $submittedNotes[(string) $id] ?? ($_SESSION['cart_notes'][(string) $id] ?? '');
        if (!is_string($note)) {
            throw new RuntimeException('Please enter a valid customization request.');
        }
        $note = trim($note);
        if (preg_match('/\A.{0,500}\z/us', $note) !== 1) {
            throw new RuntimeException('Customization requests must be 500 characters or fewer.');
        }
        $customNotes[(string) $id] = $note;
    }
    $_SESSION['cart_notes'] = $customNotes;

    $pdo->beginTransaction();
    $items = [];
    $selectProduct = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE');
    foreach ($_SESSION['cart'] as $id => $quantity) {
        $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $selectProduct->execute([(int) $id]);
        $product = $selectProduct->fetch();
        if (!$quantity || !$product || (int) $product['stock'] < $quantity) {
            throw new RuntimeException('An item in your cart is no longer available in the requested quantity.');
        }
        $product['qty'] = $quantity;
        $product['custom_note'] = $customNotes[(string) $id] ?? '';
        $items[] = $product;
        $total += (float) $product['price'] * $quantity;
    }

    $shippingFee = $fulfillmentMethod === 'delivery' && $paymentMethod === 'cod'
        ? COD_DELIVERY_FEE
        : 0.0;
    $total += $shippingFee;

    $storedAddress = $fulfillmentMethod === 'delivery' ? $deliveryAddress : null;
    $stmt = $pdo->prepare(
        "INSERT INTO orders (user_id, total, status, customer_phone, fulfillment_method, delivery_address, payment_method) "
        . "VALUES (?, ?, 'pending', ?, ?, ?, ?)"
    );
    $stmt->execute([
        $_SESSION['user_id'],
        $total,
        $customerPhone,
        $fulfillmentMethod,
        $storedAddress,
        $paymentMethod,
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, qty, price, custom_note) VALUES (?, ?, ?, ?, ?, ?)');
    $decrementStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
    foreach ($items as $item) {
        $insertItem->execute([$orderId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['custom_note']]);
        $decrementStock->execute([$item['qty'], $item['id']]);
    }
    $pdo->commit();
    unset($_SESSION['cart']);
    unset($_SESSION['cart_notes']);
    unset($_SESSION['checkout_details']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Checkout failed: ' . $exception->getMessage());
    $error = $exception instanceof RuntimeException
        ? $exception->getMessage()
        : 'Checkout failed. Please try again.';
}

require '../includes/header.php';
?>
<div class="container mt-4 text-center">
<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
  <a href="index.php" class="btn btn-outline-secondary">Return to cart</a>
<?php else: ?>
  <div class="alert alert-success" style="font-size:20px;">Order #<?= $orderId ?> placed successfully! Total: ₱<?= number_format($total, 2) ?></div>
  <a href="/EL3/products/index.php" class="btn btn-primary">Continue Shopping</a>
  <a href="/EL3/orders/index.php" class="btn btn-warning">View My Orders</a>
<?php endif; ?>
</div>
<?php require '../includes/footer.php'; ?>
