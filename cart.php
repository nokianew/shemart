<?php
require_once __DIR__ . '/functions.php';

// ------------------------------
// Handle cart actions (remove / update)
// ------------------------------

if (isset($_GET['remove'])) {
    $removeId = (int) $_GET['remove'];

    if (isset($_SESSION['cart'][$removeId])) {
        unset($_SESSION['cart'][$removeId]);
    }

    header('Location: cart.php');
    exit;
}

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $pid => $qty) {
        $pid = (int) $pid;
        $qty = max(1, (int) $qty);

        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid]['quantity'] = $qty;
        }
    }

    header('Location: cart.php');
    exit;
}

// ------------------------------
// Load cart + totals
// ------------------------------

$cart = $_SESSION['cart'] ?? [];

$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$page_title = "Your Cart";
include __DIR__ . '/partials/header.php';
?>

<h1 class="mb-4">Your Cart</h1>

<?php if (empty($cart)): ?>

  <div class="alert alert-info">
    Your cart is empty.
  </div>

  <a href="index.php" class="btn btn-primary">
    Continue Shopping
  </a>

<?php else: ?>

  <form method="post">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Product</th>
            <th class="text-center">Price</th>
            <th class="text-center" style="width:120px;">Quantity</th>
            <th class="text-end">Total</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cart as $id => $item): ?>
            <?php $lineTotal = $item['price'] * $item['quantity']; ?>
            <tr>
              <td>
                <div class="d-flex align-items-center">
                  <?php if (!empty($item['image'])): ?>
                    <img src="assets/images/<?= htmlspecialchars($item['image']); ?>"
                         alt="<?= htmlspecialchars($item['name']); ?>"
                         style="width:60px;height:60px;object-fit:cover;"
                         class="me-3 rounded">
                  <?php endif; ?>
                  <div>
                    <strong><?= htmlspecialchars($item['name']); ?></strong>
                  </div>
                </div>
              </td>

              <td class="text-center">
                ₹<?= number_format($item['price'], 2); ?>
              </td>

              <td class="text-center">
                <input type="number"
                       name="quantities[<?= (int)$id; ?>]"
                       value="<?= (int)$item['quantity']; ?>"
                       min="1"
                       class="form-control text-center">
              </td>

              <td class="text-end">
                ₹<?= number_format($lineTotal, 2); ?>
              </td>

              <td class="text-end">
                <a href="cart.php?remove=<?= (int)$id; ?>"
                   class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Remove this item from cart?');">
                  Remove
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <h4 class="mb-0">
        Subtotal: ₹<?= number_format($subtotal, 2); ?>
      </h4>

      <div class="text-end">
        <button type="submit" class="btn btn-outline-secondary me-2">
          Update Cart
        </button>
        <!-- ✅ Proceed to Checkout button -->
        <a href="checkout.php" class="btn btn-success">
          Proceed to Checkout
        </a>
      </div>
    </div>
  </form>

  <div class="mt-3">
    <a href="index.php" class="btn btn-link">
      ← Continue Shopping
    </a>
  </div>

<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
