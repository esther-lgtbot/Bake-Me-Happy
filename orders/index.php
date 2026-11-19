<?php
require '../config/db.php';
require_login();
if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: /EL3/admin/orders.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];
$message = '';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!$orderId || !in_array($action, ['cancel', 'request_receipt'], true)) {
        http_response_code(400);
        exit('Invalid order request.');
    }

    $pdo->beginTransaction();
    try {
        $order = $pdo->prepare('SELECT status, receipt_requested_at FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
        $order->execute([$orderId, $userId]);
        $currentOrder = $order->fetch();

        if (!$currentOrder) {
            $message = 'That order could not be found.';
        } elseif ($action === 'request_receipt' && $currentOrder['status'] === 'cancelled') {
            $message = 'A receipt cannot be requested for a cancelled order.';
        } elseif ($action === 'request_receipt' && $currentOrder['receipt_requested_at'] !== null) {
            $message = 'A receipt has already been requested for this order.';
        } elseif ($action === 'request_receipt') {
            $requestReceipt = $pdo->prepare(
                'UPDATE orders SET receipt_requested_at = CURRENT_TIMESTAMP '
                . 'WHERE id = ? AND user_id = ? AND status <> \'cancelled\' AND receipt_requested_at IS NULL'
            );
            $requestReceipt->execute([$orderId, $userId]);
            if ($requestReceipt->rowCount() !== 1) {
                throw new RuntimeException('The receipt could not be requested. Please try again.');
            }
            $message = 'Receipt requested. It is now available below your order.';
        } elseif ($currentOrder['status'] !== 'pending') {
            $message = 'Only pending orders can be cancelled.';
        } else {
            $items = $pdo->prepare(
                'SELECT product_id, SUM(qty) AS quantity FROM order_items '
                . 'WHERE order_id = ? AND product_id IS NOT NULL GROUP BY product_id ORDER BY product_id'
            );
            $items->execute([$orderId]);
            $restoreStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($items->fetchAll() as $item) {
                $restoreStock->execute([(int) $item['quantity'], (int) $item['product_id']]);
            }

            $cancel = $pdo->prepare('UPDATE orders SET status = \'cancelled\' WHERE id = ? AND user_id = ? AND status = \'pending\'');
            $cancel->execute([$orderId, $userId]);
            if ($cancel->rowCount() !== 1) {
                throw new RuntimeException('The order status changed before it could be cancelled.');
            }
            $message = 'Your order has been cancelled.';
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $_SESSION['order_message'] = $message;
    header('Location: /EL3/orders/index.php');
    exit;
}

if (!empty($_SESSION['order_message'])) {
    $message = $_SESSION['order_message'];
    unset($_SESSION['order_message']);
}

$orders = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$orders->execute([$userId]);
$orderList = $orders->fetchAll();
require '../includes/header.php';
?>

<style>
  .orders-wrap{ max-width:880px; margin:0 auto; }
  .orders-title{ font-weight:800; letter-spacing:-.02em; color:#3B2314; margin:0; }
  .orders-sub{ color:#7C6A5E; font-size:.92rem; margin:4px 0 0; }
  .btn-back{ border-radius:999px !important; font-weight:600; padding:8px 16px !important; color:#3B2314 !important; border-color:#E7D5C3 !important; background:#fff !important; }
  .order-card{ background:#FFFBF7; border:1px solid #F8D8B8; border-radius:16px; overflow:hidden; }
  .order-head{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; padding:14px 16px; border-bottom:1px dashed #F8D8B8; }
  .order-id{ font-weight:800; color:#3B2314; margin:0; }
  .order-date{ color:#7C6A5E; font-size:12.5px; margin:2px 0 0; }
  .order-total{ text-align:right; }
  .order-total small{ color:#7C6A5E; font-size:12px; display:block; }
  .order-total b{ color:#C2410C; font-size:1.1rem; }
  .badge-status{ font-size:11.5px; font-weight:700; padding:5px 11px; border-radius:999px; text-transform:capitalize; }
  .status-pending{ background:#FFF3E0; color:#9A3412; }
  .status-processing{ background:#E3F2FD; color:#1565C0; }
  .status-accepted{ background:#E3F2FD; color:#1565C0; }
  .status-completed{ background:#EAF7EC; color:#2E7D32; }
  .status-cancelled{ background:#F3E8E8; color:#9E2A2A; }
  .status-default{ background:#F5F0EB; color:#3B2314; }
  .order-item{ display:flex; justify-content:space-between; align-items:center; gap:12px; padding:10px 16px; }
  .order-item + .order-item{ border-top:1px solid #FDF3E7; }
  .item-name{ font-weight:600; color:#3B2314; margin:0; font-size:14.5px; }
  .item-qty{ color:#7C6A5E; font-size:12.5px; margin:2px 0 0; }
  .item-price{ font-weight:700; color:#3B2314; white-space:nowrap; }
  .empty-box{ background:#FFFBF7; border:1px dashed #F8D8B8; border-radius:16px; padding:36px; text-align:center; }
  .btn-shop{ background:#C2410C !important; border-color:#C2410C !important; color:#fff !important; border-radius:999px !important; font-weight:700; padding:10px 22px !important; }
</style>

<div class="container mt-4 orders-wrap" style="padding-top:15px;">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h2 class="orders-title">My Orders</h2>
      <p class="orders-sub">Track your cakes and past orders.</p>
    </div>
    <a href="/EL3/products/index.php" class="btn btn-back btn-outline-secondary btn-sm">Back to Products</a>
  </div>

  <?php if ($message !== ''): ?>
    <div class="alert alert-info mt-3"><?= h($message) ?></div>
  <?php endif; ?>

  <?php if (!$orderList): ?>
    <div class="empty-box mt-4">
      <div style="font-weight:800; color:#3B2314; font-size:1.1rem;">Wala ka pang order.</div>
      <p style="color:#7C6A5E; font-size:14px; margin:6px 0 16px;">Your cakes will appear here after checkout.</p>
      <a href="/EL3/products/index.php" class="btn btn-shop" style="text-decoration:none;">Browse Cakes</a>
    </div>
  <?php else: ?>
    <div class="d-flex flex-column gap-3 mt-4">
    <?php foreach ($orderList as $o): ?>
      <?php
      $status = strtolower(trim($o['status'] ?? 'pending'));
      $itemsQuery = $pdo->prepare(
          'SELECT oi.*, COALESCE(p.name, oi.product_name) AS item_name '
          . 'FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id '
          . 'WHERE oi.order_id = ? ORDER BY oi.id'
      );
      $itemsQuery->execute([$o['id']]);
      $orderItems = $itemsQuery->fetchAll();
      ?>
      <?php $statusClass = $status === 'pending' ? 'status-pending' : (in_array($status, ['preparing', 'ready_for_pickup', 'out_for_delivery', 'processing', 'accepted'], true) ? 'status-accepted' : ($status === 'completed' ? 'status-completed' : ($status === 'cancelled' ? 'status-cancelled' : 'status-default'))); ?>
      <div class="order-card">
        <div class="order-head">
          <div>
            <p class="order-id">Order #<?= $o['id'] ?></p>
            <p class="order-date"><?= date('M d, Y • h:i A', strtotime($o['created_at'])) ?></p>
            <?php if (!empty($o['customer_phone'])): ?>
              <p class="order-date mb-0"><strong>Phone:</strong> <?= h($o['customer_phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($o['fulfillment_method'])): ?>
              <p class="order-date mb-0"><strong>Fulfillment:</strong> <?= h($fulfillmentLabels[$o['fulfillment_method']] ?? $o['fulfillment_method']) ?></p>
            <?php endif; ?>
            <?php if (!empty($o['delivery_address'])): ?>
              <p class="order-date mb-0"><strong>Delivery address:</strong> <?= nl2br(h($o['delivery_address'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($o['payment_method'])): ?>
              <p class="order-date mb-0"><strong>Payment:</strong> <?= h($paymentLabels[$o['payment_method']] ?? $o['payment_method']) ?></p>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="badge-status <?= $statusClass ?>"><?= h($statusLabels[$status] ?? ucfirst($status)) ?></span>
            <?php if ($status === 'pending'): ?>
              <form method="POST" onsubmit="return confirm('Cancel this order?');">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel order</button>
              </form>
            <?php endif; ?>
            <?php if ($status !== 'cancelled' && empty($o['receipt_requested_at'])): ?>
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                <input type="hidden" name="action" value="request_receipt">
                <button type="submit" class="btn btn-sm btn-outline-primary">Request receipt</button>
              </form>
            <?php endif; ?>
            <div class="order-total">
              <small>Total</small>
              <b>₱<?= number_format((float)$o['total'],2) ?></b>
            </div>
          </div>
        </div>

        <div>
          <?php foreach ($orderItems as $it): ?>
            <div class="order-item">
              <div>
                <p class="item-name"><?= h($it['item_name']) ?></p>
                <p class="item-qty">Qty <?= (int) $it['qty'] ?> &bull; ₱<?= number_format((float) $it['price'],2) ?> each</p>
                <?php if (!empty($it['custom_note'])): ?>
                  <p class="item-qty mb-0"><strong>Customization request:</strong> <?= nl2br(h($it['custom_note'])) ?></p>
                <?php endif; ?>
              </div>
              <div class="item-price">₱<?= number_format((float) $it['price'] * (int)$it['qty'], 2) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!empty($o['receipt_requested_at'])): ?>
          <div class="m-3 p-3 border rounded bg-white">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
              <div>
                <h3 class="h5 mb-1">Order receipt</h3>
                <div class="text-muted small">Receipt requested <?= h(date('M d, Y h:i A', strtotime($o['receipt_requested_at']))) ?></div>
              </div>
              <strong>Order #<?= (int) $o['id'] ?></strong>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-2">
                <thead><tr><th>Product</th><th>Qty</th><th>Unit price</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($orderItems as $receiptItem): ?>
                  <tr>
                    <td><?= h($receiptItem['item_name']) ?></td>
                    <td><?= (int) $receiptItem['qty'] ?></td>
                    <td>₱<?= number_format((float) $receiptItem['price'], 2) ?></td>
                    <td class="text-end">₱<?= number_format((float) $receiptItem['price'] * (int) $receiptItem['qty'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($o['fulfillment_method'] === 'delivery' && $o['payment_method'] === 'cod'): ?>
                  <tr>
                    <td colspan="3">COD delivery fee</td>
                    <td class="text-end">₱<?= number_format(COD_DELIVERY_FEE, 2) ?></td>
                  </tr>
                <?php endif; ?>
                </tbody>
                <tfoot><tr><th colspan="3" class="text-end">Order total</th><th class="text-end">₱<?= number_format((float) $o['total'], 2) ?></th></tr></tfoot>
              </table>
            </div>
            <div class="small text-muted">
              <?= !empty($o['fulfillment_method']) ? h($fulfillmentLabels[$o['fulfillment_method']] ?? $o['fulfillment_method']) : '' ?>
              <?= !empty($o['payment_method']) ? ' · ' . h($paymentLabels[$o['payment_method']] ?? $o['payment_method']) : '' ?>
            </div>
            <div class="small text-muted mt-2">Order receipt only; it does not confirm that payment has been received.</div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>