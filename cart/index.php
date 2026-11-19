<?php require '../config/db.php'; if (in_array($_SESSION['role'] ?? '', ['seller', 'admin'], true)) { header('Location: ' . (($_SESSION['role'] ?? '') === 'admin' ? '/EL3/admin/orders.php' : '/EL3/seller/index.php')); exit; } require '../includes/header.php'; ?>

<style>
  .cart-wrap{ max-width:880px; margin:0 auto; }
  .cart-title{ font-weight:800; letter-spacing:-.02em; color:#3B2314; margin:0; }
  .cart-sub{ color:#7C6A5E; font-size:.92rem; margin:4px 0 0; }
  .btn-back{ border-radius:999px !important; font-weight:600; padding:8px 16px !important; color:#3B2314 !important; border-color:#E7D5C3 !important; background:#fff !important; }
  .cart-item{ background:#FFFBF7; border:1px solid #F8D8B8; border-radius:16px; padding:14px; display:flex; gap:14px; align-items:center; }
  .cart-img{ width:76px; height:76px; object-fit:cover; border-radius:12px; background:#FDF3E7; flex-shrink:0; }
  .cart-img-empty{ width:76px; height:76px; border-radius:12px; background:#FDF3E7; display:flex; align-items:center; justify-content:center; font-weight:800; color:#A05A2C; font-size:12px; text-align:center; flex-shrink:0; }
  .cart-name{ font-weight:800; color:#3B2314; margin:0; line-height:1.3; }
  .cart-each{ color:#7C6A5E; font-size:13px; margin:2px 0 0; }
  .qty-btn{ width:32px; height:32px; border-radius:999px !important; padding:0 !important; font-weight:800; line-height:1; border-color:#E7D5C3 !important; color:#3B2314 !important; background:#fff !important; }
  .qty-btn:hover{ background:#FFF6EE !important; }
  .btn-remove{ border-radius:999px !important; font-size:12.5px; font-weight:600; padding:6px 12px !important; }
  .cart-subtotal{ font-weight:800; color:#3B2314; white-space:nowrap; }
  .summary-box{ background:#FFFBF7; border:1px solid #F8D8B8; border-radius:16px; padding:18px; position:sticky; top:90px; }
  .summary-row{ display:flex; justify-content:space-between; font-size:14px; color:#7C6A5E; margin-bottom:8px; }
  .summary-total{ display:flex; justify-content:space-between; align-items:center; border-top:1px dashed #F8D8B8; padding-top:12px; margin-top:12px; }
  .summary-total b{ font-size:1.25rem; color:#C2410C; }
  .btn-checkout{ background:#C2410C !important; border-color:#C2410C !important; color:#fff !important; border-radius:999px !important; font-weight:700; padding:12px !important; }
  .btn-checkout:hover{ background:#9A3412 !important; }
  .empty-box{ background:#FFFBF7; border:1px dashed #F8D8B8; border-radius:16px; padding:36px; text-align:center; }
</style>

<?php $checkoutDetails = $_SESSION['checkout_details'] ?? []; ?>
<div class="container mt-4 cart-wrap" style="padding-top:15px;">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <h2 class="cart-title">Your Cart</h2>
      <p class="cart-sub">Review your cakes before checkout.</p>
    </div>
    <a href="/EL3/products/index.php" class="btn btn-back btn-outline-secondary btn-sm">Back to Products</a>
  </div>

  <?php if (!empty($_SESSION['cart_error'])): ?><div class="alert alert-warning mt-3" style="border-radius:12px;"><?= h($_SESSION['cart_error']) ?></div><?php unset($_SESSION['cart_error']); endif; ?>

  <?php if(empty($_SESSION['cart'])): ?>
    <div class="empty-box mt-4">
      <div style="font-weight:800; color:#3B2314; font-size:1.1rem;">Your cart is empty</div>
      <p style="color:#7C6A5E; font-size:14px; margin:6px 0 16px;">No cakes yet. Let's fix that.</p>
      <a href="/EL3/products/index.php" class="btn btn-checkout" style="padding:10px 22px !important; text-decoration:none;">Browse Cakes</a>
    </div>
  <?php else: $total=0; $count=0; ?>
    <div class="row g-3 mt-1">
      <div class="col-lg-8">
        <div class="d-flex flex-column gap-3">
          <?php foreach($_SESSION['cart'] as $id=>$qty): $s=$pdo->prepare("SELECT * FROM products WHERE id=?"); $s->execute([$id]); $p=$s->fetch(); if(!$p) continue; $sub=$p['price']*$qty; $total+=$sub; $count+=$qty; ?>
            <div class="cart-item">
              <?php if (!empty($p['image'])): ?>
                <img src="<?= APP_BASE ?>/uploads/<?= rawurlencode($p['image']) ?>" alt="<?= h($p['name']) ?>" class="cart-img">
              <?php else: ?>
                <div class="cart-img-empty">Bake Me<br>Happy</div>
              <?php endif; ?>

              <div class="flex-grow-1" style="min-width:0;">
                <p class="cart-name"><?= h($p['name']) ?></p>
                <p class="cart-each">₱<?= number_format((float)$p['price'],2) ?> each</p>
                <label for="custom-note-<?= (int) $id ?>" class="form-label small mt-2 mb-1">Customization request (optional)</label>
                <textarea
                  id="custom-note-<?= (int) $id ?>"
                  name="notes[<?= (int) $id ?>]"
                  form="checkout-form"
                  class="form-control form-control-sm"
                  rows="2"
                  maxlength="500"
                  placeholder="Send request note to the seller"
                ><?= h($_SESSION['cart_notes'][(string) $id] ?? '') ?></textarea>

                <form method="POST" action="update.php" class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="id" value="<?= (int) $id ?>">
                  <button name="action" value="minus" class="btn btn-outline-secondary qty-btn" aria-label="Decrease quantity">-</button>
                  <b style="min-width:22px; text-align:center;"><?= (int) $qty ?></b>
                  <button name="action" value="plus" class="btn btn-outline-secondary qty-btn" aria-label="Increase quantity">+</button>
                  <button name="action" value="remove" class="btn btn-outline-danger btn-remove ms-1">Remove</button>
                </form>
              </div>

              <div class="cart-subtotal">₱<?= number_format($sub,2) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-lg-4">
        <?php
        $initialShippingFee = ($checkoutDetails['fulfillment_method'] ?? 'pickup') === 'delivery'
            && ($checkoutDetails['payment_method'] ?? '') === 'cod'
            ? COD_DELIVERY_FEE
            : 0.0;
        ?>
        <div class="summary-box">
          <div style="font-weight:800; color:#3B2314; margin-bottom:12px;">Order Summary</div>
          <div class="summary-row"><span>Items (<?= $count ?>)</span><span>₱<?= number_format($total,2) ?></span></div>
          <div class="summary-row"><span>Fulfillment</span><span>Choose below</span></div>
          <div class="summary-row"><span>COD delivery fee</span><span id="shipping-fee">₱<?= number_format($initialShippingFee, 2) ?></span></div>
          <div class="summary-total"><span style="font-weight:700; color:#3B2314;">Total</span><b id="order-total">₱<?= number_format($total + $initialShippingFee, 2) ?></b></div>

          <form id="checkout-form" method="POST" action="checkout.php" class="mt-3">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <div class="mb-3">
              <label for="customer-phone" class="form-label">Phone number</label>
              <input
                type="tel"
                id="customer-phone"
                name="customer_phone"
                class="form-control"
                value="<?= h($checkoutDetails['customer_phone'] ?? '') ?>"
                maxlength="30"
                autocomplete="tel"
                placeholder="e.g. 09xx xxx xxxx"
                required
              >
            </div>
            <div class="mb-3">
              <label for="fulfillment-method" class="form-label">How would you like to receive your order?</label>
              <select id="fulfillment-method" name="fulfillment_method" class="form-select" required>
                <option value="pickup" <?= ($checkoutDetails['fulfillment_method'] ?? 'pickup') === 'pickup' ? 'selected' : '' ?>>Pick up at the shop</option>
                <option value="delivery" <?= ($checkoutDetails['fulfillment_method'] ?? '') === 'delivery' ? 'selected' : '' ?>>Deliver to me</option>
              </select>
            </div>
            <div id="delivery-address-field" class="mb-3" <?= ($checkoutDetails['fulfillment_method'] ?? 'pickup') === 'delivery' ? '' : 'hidden' ?>>
              <label for="delivery-address" class="form-label">Delivery address</label>
              <textarea
                id="delivery-address"
                name="delivery_address"
                class="form-control"
                rows="3"
                maxlength="1000"
                autocomplete="street-address"
                placeholder="Enter your complete delivery address"
                <?= ($checkoutDetails['fulfillment_method'] ?? 'pickup') === 'delivery' ? 'required' : '' ?>
              ><?= h($checkoutDetails['delivery_address'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
              <label for="payment-method" class="form-label">Payment method</label>
              <select id="payment-method" name="payment_method" class="form-select" required>
                <option value="">Choose a payment method</option>
                <option value="gcash" <?= ($checkoutDetails['payment_method'] ?? '') === 'gcash' ? 'selected' : '' ?>>GCash</option>
                <option value="bank_transfer" <?= ($checkoutDetails['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Bank transfer</option>
                <option value="pay_in_store" <?= ($checkoutDetails['payment_method'] ?? '') === 'pay_in_store' ? 'selected' : '' ?>>Pay in the store (pickup)</option>
                <option value="cod" <?= ($checkoutDetails['payment_method'] ?? '') === 'cod' ? 'selected' : '' ?>>Cash on delivery</option>
              </select>
              <div class="form-text">Selecting GCash or bank transfer records your preference; payment is arranged with the seller.</div>
            </div>
            <button class="btn btn-checkout w-100">Proceed to Checkout</button>
          </form>
          <a href="/EL3/products/index.php" class="d-block text-center mt-2" style="font-size:13px; color:#7C6A5E; text-decoration:none;">Continue shopping</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  const fulfillmentMethod = document.getElementById('fulfillment-method');
  if (fulfillmentMethod) {
    const addressField = document.getElementById('delivery-address-field');
    const deliveryAddress = document.getElementById('delivery-address');
    const paymentMethod = document.getElementById('payment-method');
    const shippingFee = document.getElementById('shipping-fee');
    const orderTotal = document.getElementById('order-total');
    const itemsTotal = <?= json_encode((float) $total) ?>;
    const codDeliveryFee = <?= json_encode((float) COD_DELIVERY_FEE) ?>;
    const storePaymentOption = paymentMethod.querySelector('option[value="pay_in_store"]');
    const deliveryPaymentOption = paymentMethod.querySelector('option[value="cod"]');

    function updateCheckoutOptions() {
      const isDelivery = fulfillmentMethod.value === 'delivery';
      addressField.hidden = !isDelivery;
      deliveryAddress.required = isDelivery;
      storePaymentOption.hidden = isDelivery;
      storePaymentOption.disabled = isDelivery;
      deliveryPaymentOption.hidden = !isDelivery;
      deliveryPaymentOption.disabled = !isDelivery;
      if ((isDelivery && paymentMethod.value === 'pay_in_store')
        || (!isDelivery && paymentMethod.value === 'cod')) {
        paymentMethod.value = '';
      }
      const fee = isDelivery && paymentMethod.value === 'cod' ? codDeliveryFee : 0;
      shippingFee.textContent = `₱${fee.toFixed(2)}`;
      orderTotal.textContent = `₱${(itemsTotal + fee).toFixed(2)}`;
    }

    fulfillmentMethod.addEventListener('change', updateCheckoutOptions);
    paymentMethod.addEventListener('change', updateCheckoutOptions);
    updateCheckoutOptions();
  }
</script>

<?php require '../includes/footer.php'; ?>