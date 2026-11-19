<?php
require '../config/db.php';
require_role('admin');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $requestedStatus = $_POST['status'] ?? '';
    $statusMap = [
        'pending' => 'pending',
        'paid' => 'paid',
        'shipped' => 'shipped',
        'accepted' => 'accepted',
        'preparing' => 'preparing',
        'ready_for_pickup' => 'ready_for_pickup',
        'out_for_delivery' => 'out_for_delivery',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];

    if (!$orderId || !isset($statusMap[$requestedStatus])) {
        http_response_code(400);
        exit('Invalid order status request.');
    }

    $pdo->beginTransaction();
    try {
        $findOrder = $pdo->prepare(
            'SELECT status, fulfillment_method, payment_method FROM orders WHERE id = ? FOR UPDATE'
        );
        $findOrder->execute([$orderId]);
        $order = $findOrder->fetch();

        if (!$order) {
            $message = 'Order not found.';
        } elseif ($requestedStatus === 'ready_for_pickup' && $order['fulfillment_method'] !== 'pickup') {
            $message = 'Ready for pickup is only available for store pickup orders.';
        } elseif ($requestedStatus === 'out_for_delivery'
            && ($order['fulfillment_method'] !== 'delivery' || $order['payment_method'] !== 'cod')) {
            $message = 'Out for delivery is only available for cash-on-delivery orders.';
        } else {
            $currentStatus = $order['status'];
            $newStatus = $statusMap[$requestedStatus];

            if ($currentStatus !== $requestedStatus) {
                if ($requestedStatus === 'cancelled') {
                    $items = $pdo->prepare(
                        'SELECT product_id, SUM(qty) AS quantity FROM order_items '
                        . 'WHERE order_id = ? AND product_id IS NOT NULL '
                        . 'GROUP BY product_id ORDER BY product_id'
                    );
                    $items->execute([$orderId]);
                    $restoreStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                    foreach ($items->fetchAll() as $item) {
                        $restoreStock->execute([(int) $item['quantity'], (int) $item['product_id']]);
                    }
                } elseif ($currentStatus === 'cancelled') {
                    $items = $pdo->prepare(
                        'SELECT product_id, SUM(qty) AS quantity FROM order_items '
                        . 'WHERE order_id = ? AND product_id IS NOT NULL '
                        . 'GROUP BY product_id ORDER BY product_id'
                    );
                    $items->execute([$orderId]);
                    $checkStock = $pdo->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                    $reduceStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                    foreach ($items->fetchAll() as $item) {
                        $productId = (int) $item['product_id'];
                        $quantity = (int) $item['quantity'];
                        $checkStock->execute([$productId]);
                        $stock = $checkStock->fetchColumn();
                        if ($stock !== false) {
                            if ((int) $stock < $quantity) {
                                throw new RuntimeException('Not enough stock to reactivate this cancelled order.');
                            }
                            $reduceStock->execute([$quantity, $productId]);
                        }
                    }
                }

                $update = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
                $update->execute([$newStatus, $orderId]);
                $message = 'Order status updated.';
            } else {
                $message = 'Order status is unchanged.';
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof RuntimeException) {
            $message = $exception->getMessage();
        } else {
            throw $exception;
        }
    }

    $_SESSION['admin_order_message'] = $message;
    header('Location: /EL3/admin/orders.php');
    exit;
}

if (!empty($_SESSION['admin_order_message'])) {
    $message = $_SESSION['admin_order_message'];
    unset($_SESSION['admin_order_message']);
}

$orders = $pdo->query(
    'SELECT o.*, u.name AS customer_name FROM orders o '
    . 'JOIN users u ON u.id = o.user_id ORDER BY o.id DESC'
)->fetchAll();
require '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h2 class="mb-1">Manage Customer Orders</h2>
    
  </div>
</div>

<?php if ($message !== ''): ?>
  <div class="alert alert-info"><?= h($message) ?></div>
<?php endif; ?>

<?php if (!$orders): ?>
  <div class="alert alert-info">There are no customer orders yet.</div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Order</th>
          <th>Buyer</th>
          <th>Items ordered</th>
          <th>Total price</th>
          <th>Date and time</th>
          <th>Status</th>
          <th>Update status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $order): ?>
        <?php
          $displayStatus = $order['status'];
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
          if ($order['fulfillment_method'] !== 'pickup') {
              unset($statusLabels['ready_for_pickup']);
          }
          if ($order['fulfillment_method'] !== 'delivery' || $order['payment_method'] !== 'cod') {
              unset($statusLabels['out_for_delivery']);
          }
          $items = $pdo->prepare(
              'SELECT COALESCE(p.name, oi.product_name) AS item_name, oi.qty '
              . 'FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id '
              . 'WHERE oi.order_id = ? ORDER BY oi.id'
          );
          $items->execute([$order['id']]);
        ?>
        <tr>
          <td>#<?= (int) $order['id'] ?></td>
          <td>
            <?= h($order['customer_name']) ?>
            <?php if (!empty($order['customer_phone'])): ?>
              <br><small>Phone: <?= h($order['customer_phone']) ?></small>
            <?php endif; ?>
            <?php if (!empty($order['fulfillment_method'])): ?>
              <br><small><?= h(ucfirst($order['fulfillment_method'])) ?><?= !empty($order['payment_method']) ? ' - ' . h(str_replace('_', ' ', ucfirst($order['payment_method']))) : '' ?></small>
            <?php endif; ?>
            <?php if (!empty($order['delivery_address'])): ?>
              <br><small>Address: <?= nl2br(h($order['delivery_address'])) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <ul class="mb-0 ps-3">
              <?php foreach ($items as $item): ?>
                <li><?= h($item['item_name']) ?> × <?= (int) $item['qty'] ?></li>
              <?php endforeach; ?>
            </ul>
          </td>
          <td>₱<?= number_format((float) $order['total'], 2) ?></td>
          <td><?= h(date('M d, Y h:i A', strtotime($order['created_at']))) ?></td>
          <td><span class="badge text-bg-<?= $displayStatus === 'completed' ? 'success' : ($displayStatus === 'cancelled' ? 'danger' : 'warning') ?>"><?= h($statusLabels[$displayStatus] ?? ucfirst($displayStatus)) ?></span></td>
          <td>
            <form method="POST" class="d-flex gap-2">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <select name="status" class="form-select form-select-sm" aria-label="Order status">
                <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
                  <option value="<?= h($statusValue) ?>" <?= $displayStatus === $statusValue ? 'selected' : '' ?>><?= h($statusLabel) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-primary btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>
