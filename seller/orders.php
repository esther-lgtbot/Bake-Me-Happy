<?php
require '../config/db.php';
require_role('seller');
$sellerId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $requestedStatus = $_POST['status'] ?? '';
    $allowedStatuses = ['pending', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'completed'];
    if (!$orderId || $action !== 'update_status' || !in_array($requestedStatus, $allowedStatuses, true)) {
        http_response_code(400);
        exit('Invalid order status request.');
    }

    $pdo->beginTransaction();
    try {
        $findOrder = $pdo->prepare(
            'SELECT o.status, o.fulfillment_method, o.payment_method FROM orders o '
            . 'WHERE o.id = ? AND EXISTS ('
            . 'SELECT 1 FROM order_items oi JOIN products p ON p.id = oi.product_id '
            . 'WHERE oi.order_id = o.id AND p.seller_id = ?) FOR UPDATE'
        );
        $findOrder->execute([$orderId, $sellerId]);
        $order = $findOrder->fetch();
        $message = '';

        if (!$order) {
            $message = 'Order not found.';
        } elseif ($order['status'] === 'cancelled') {
            $message = 'Cancelled orders cannot be updated.';
        } elseif ($requestedStatus === 'ready_for_pickup' && $order['fulfillment_method'] !== 'pickup') {
            $message = 'Ready for pickup is only available for store pickup orders.';
        } elseif ($requestedStatus === 'out_for_delivery'
            && ($order['fulfillment_method'] !== 'delivery' || $order['payment_method'] !== 'cod')) {
            $message = 'Out for delivery is only available for cash-on-delivery orders.';
        } else {
            $update = $pdo->prepare(
                'UPDATE orders SET status = ? WHERE id = ? AND status <> \'cancelled\''
            );
            $update->execute([$requestedStatus, $orderId]);
            $message = $update->rowCount() > 0 ? 'Order status updated.' : 'Order status is unchanged.';
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $_SESSION['seller_order_message'] = $message;
    header('Location: orders.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT DISTINCT o.*, u.name AS buyer_name FROM orders o '
    . 'JOIN users u ON u.id = o.user_id '
    . 'JOIN order_items oi ON oi.order_id = o.id '
    . 'JOIN products p ON p.id = oi.product_id '
    . 'WHERE p.seller_id = ? ORDER BY o.id DESC'
);
$stmt->execute([$sellerId]);
$orders = $stmt->fetchAll();
$fulfillmentLabels = ['pickup' => 'Pickup', 'delivery' => 'Delivery'];
$paymentLabels = [
    'gcash' => 'GCash',
    'cod' => 'Cash on delivery',
    'bank_transfer' => 'Bank transfer',
    'pay_in_store' => 'Pay in the store',
];
$statusLabels = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'shipped' => 'Shipped',
    'accepted' => 'Accepted',
    'preparing' => 'Preparing',
    'ready_for_pickup' => 'Ready for pickup',
    'out_for_delivery' => 'Out for delivery',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
$message = $_SESSION['seller_order_message'] ?? '';
unset($_SESSION['seller_order_message']);
require '../includes/header.php';
?>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Customer Orders</h2>
    <a href="index.php" class="btn btn-outline-secondary">My Products</a>
  </div>
  <?php if ($message !== ''): ?>
    <div class="alert alert-info"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if (!$orders): ?>
    <div class="alert alert-info">No orders for your products yet.</div>
  <?php endif; ?>
  <?php foreach ($orders as $order): ?>
    <section class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <h5>Order #<?= (int) $order['id'] ?></h5>
            <p class="text-muted mb-1">Buyer: <?= h($order['buyer_name']) ?></p>
            <?php if (!empty($order['customer_phone'])): ?>
              <p class="mb-1"><strong>Phone:</strong> <a href="tel:<?= h($order['customer_phone']) ?>"><?= h($order['customer_phone']) ?></a></p>
            <?php endif; ?>
            <?php if (!empty($order['fulfillment_method'])): ?>
              <p class="mb-1"><strong>Fulfillment:</strong> <?= h($fulfillmentLabels[$order['fulfillment_method']] ?? $order['fulfillment_method']) ?></p>
            <?php endif; ?>
            <?php if (!empty($order['delivery_address'])): ?>
              <p class="mb-1"><strong>Delivery address:</strong> <?= nl2br(h($order['delivery_address'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($order['payment_method'])): ?>
              <p class="mb-1"><strong>Payment:</strong> <?= h($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></p>
            <?php endif; ?>
            <p class="text-muted">Placed <?= h($order['created_at']) ?></p>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge <?= $order['status'] === 'completed' ? 'text-bg-success' : ($order['status'] === 'cancelled' ? 'text-bg-danger' : 'text-bg-warning') ?>">
              <?= h($statusLabels[$order['status']] ?? ucfirst($order['status'])) ?>
            </span>
            <?php if ($order['status'] !== 'cancelled'): ?>
              <form method="POST" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                <input type="hidden" name="action" value="update_status">
                <select name="status" class="form-select form-select-sm" aria-label="Update order status">
                  <?php if (in_array($order['status'], ['paid', 'shipped', 'accepted'], true)): ?>
                    <option value="<?= h($order['status']) ?>" selected><?= h($statusLabels[$order['status']]) ?></option>
                  <?php endif; ?>
                  <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                  <?php if ($order['fulfillment_method'] === 'pickup'): ?>
                    <option value="ready_for_pickup" <?= $order['status'] === 'ready_for_pickup' ? 'selected' : '' ?>>Ready for pickup</option>
                  <?php endif; ?>
                  <?php if ($order['fulfillment_method'] === 'delivery' && $order['payment_method'] === 'cod'): ?>
                    <option value="out_for_delivery" <?= $order['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for delivery</option>
                  <?php endif; ?>
                  <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
                <button class="btn btn-primary btn-sm">Save</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Your Product</th><th>Quantity</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php
            $items = $pdo->prepare(
                'SELECT oi.*, COALESCE(p.name, oi.product_name) AS item_name FROM order_items oi '
                . 'LEFT JOIN products p ON p.id = oi.product_id '
                . 'WHERE oi.order_id = ? AND p.seller_id = ?'
            );
            $items->execute([$order['id'], $sellerId]);
            $sellerTotal = 0.0;
            foreach ($items as $item):
                $subtotal = (float) $item['price'] * (int) $item['qty'];
                $sellerTotal += $subtotal;
            ?>
              <tr>
                <td><?= h($item['item_name']) ?></td>
                <td><?= (int) $item['qty'] ?></td>
                <td>₱<?= number_format((float) $item['price'], 2) ?></td>
                <td>₱<?= number_format($subtotal, 2) ?></td>
              </tr>
              <?php if (!empty($item['custom_note'])): ?>
                <tr>
                  <td colspan="4">
                    <strong>Customer request:</strong>
                    <?= nl2br(h($item['custom_note'])) ?>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3">Your items total</th><th>₱<?= number_format($sellerTotal, 2) ?></th></tr></tfoot>
          </table>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<?php require '../includes/footer.php'; ?>
