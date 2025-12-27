<?php
require_once __DIR__ . '/functions.php';

/**
 * Checkout page
 * NOTE:
 * - This file ONLY collects data and sends it to place_order.php
 * - No DB writes happen here
 */

// User must be logged in
requireUser();
$user = currentUser();

// Get cart
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

// Calculate total_amount server-side (defensive)
$total_amount = 0;
foreach ($cart as $item) {
    $price = isset($item['price']) ? (float)$item['price'] : 0.0;
    $qty   = isset($item['quantity']) ? (int)$item['quantity'] : 1;
    $total_amount += $price * $qty;
}

// Prefill form values
$shipping_name  = $user['name']  ?? '';
$shipping_email = $user['email'] ?? '';
$shipping_phone = $user['phone'] ?? '';
$addr1          = $user['address_line1'] ?? '';
$addr2          = $user['address_line2'] ?? '';
$city           = $user['city'] ?? '';
$state          = $user['state'] ?? '';
$postal_code    = $user['postal_code'] ?? '';
$country        = $user['country'] ?? 'India';

$page_title = 'Checkout';
include __DIR__ . '/partials/header.php';
?>

<div class="row">
  <div class="col-md-7 mb-4">
    <h1 class="h3 mb-3">Checkout</h1>

    <form id="checkoutForm" novalidate>
      <div id="formErrors" class="alert alert-danger d-none"></div>

      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" id="shipping_name" class="form-control"
               value="<?= htmlspecialchars($shipping_name) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" id="shipping_email" class="form-control"
               value="<?= htmlspecialchars($shipping_email) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" id="shipping_phone" class="form-control"
               value="<?= htmlspecialchars($shipping_phone) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Address Line 1</label>
        <input type="text" id="address_line1" class="form-control"
               value="<?= htmlspecialchars($addr1) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Address Line 2 (optional)</label>
        <input type="text" id="address_line2" class="form-control"
               value="<?= htmlspecialchars($addr2) ?>">
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">City</label>
          <input type="text" id="city" class="form-control"
                 value="<?= htmlspecialchars($city) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">State</label>
          <input type="text" id="state" class="form-control"
                 value="<?= htmlspecialchars($state) ?>" required>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">PIN / Postal Code</label>
          <input type="text" id="postal_code" class="form-control"
                 value="<?= htmlspecialchars($postal_code) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Country</label>
          <input type="text" id="country" class="form-control"
                 value="<?= htmlspecialchars($country) ?>" required>
        </div>
      </div>

      <hr>

      <h5 class="mb-2">Payment Method</h5>
      <div class="mb-3">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="payment_method" value="COD" checked>
          <label class="form-check-label">Cash on Delivery (COD)</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="payment_method" value="RAZORPAY">
          <label class="form-check-label">Pay Online (Razorpay)</label>
        </div>
      </div>

      <button id="placeOrderBtn" type="submit" class="btn btn-primary btn-lg mt-2">
        Place Order
      </button>
    </form>
  </div>

  <div class="col-md-5">
    <h2 class="h5 mb-3">Order Summary</h2>

    <div class="card mb-3">
      <div class="card-body">
        <?php foreach ($cart as $item): ?>
          <div class="d-flex justify-content-between mb-2">
            <div>
              <strong><?= htmlspecialchars($item['name']) ?></strong><br>
              <small class="text-muted">
                Qty: <?= (int)$item['quantity'] ?> × ₹<?= number_format($item['price'], 2) ?>
              </small>
            </div>
            <div>
              ₹<?= number_format($item['price'] * $item['quantity'], 2) ?>
            </div>
          </div>
        <?php endforeach; ?>

        <hr>
        <div class="d-flex justify-content-between">
          <span>Total</span>
          <strong>₹<?= number_format($total_amount, 2) ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const CART = <?= json_encode(array_values($cart)) ?>;
  const form = document.getElementById('checkoutForm');
  const errors = document.getElementById('formErrors');

  function showError(msg){
    errors.classList.remove('d-none');
    errors.innerText = msg;
  }

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    errors.classList.add('d-none');

    const payment_method = document.querySelector('input[name="payment_method"]:checked').value;

    const email = shipping_email.value.trim();

   const payload = {
  customer_email: email,   // ✅ THIS FIXES ADMIN EMAIL
  cart: CART.map(i => ({ product_id: i.id, quantity: i.quantity })),
  payment_method: payment_method,
  address: {
    name: shipping_name.value,
    phone: shipping_phone.value,
    email: email,          // ✅ BACKUP (GOOD PRACTICE)
    address_line1: address_line1.value,
    address_line2: address_line2.value,
    city: city.value,
    state: state.value,
    postal_code: postal_code.value,
    country: country.value
  }
};


    const res = await fetch('place_order.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (data.error) {
      showError(data.error);
      return;
    }

    window.location.href = 'order_success.php?id=' + data.order_id;
  });
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
