<?php
require_once __DIR__ . '/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = getProductById($id);

// If product not found or inactive
if (!$product) {
    $page_title = "Product not found";
} else {
    $page_title = $product['name'];
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    // Optional: block add-to-cart when out of stock
    if (!isset($product['stock']) || (int)$product['stock'] > 0) {
        $item = [
            'id'       => $product['id'],
            'name'     => $product['name'],
            'price'    => $product['price'],
            'quantity' => $qty,
            'image'    => $product['image'],
        ];

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$product['id']])) {
            $_SESSION['cart'][$product['id']]['quantity'] += $qty;
        } else {
            $_SESSION['cart'][$product['id']] = $item;
        }

        header("Location: cart.php");
        exit;
    }
}

include __DIR__ . '/partials/header.php';
?>

<?php if (!$product): ?>
  <p>Product not found.</p>
<?php else: ?>

<?php
    // Safe image filename with fallback
    $imgFile = !empty($product['image']) ? $product['image'] : 'placeholder.jpg';
?>

<div class="row">
  <div class="col-md-5 mb-3">
    <!-- IMPORTANT: relative path from womenshop/product.php -->
    <img src="assets/images/<?= htmlspecialchars($imgFile); ?>" 
         class="img-fluid rounded" 
         alt="<?= htmlspecialchars($product['name']); ?>">
  </div>

  <div class="col-md-7">
    <h1><?= htmlspecialchars($product['name']); ?></h1>
    <p class="text-muted"><?= htmlspecialchars($product['category_name']); ?></p>
    <h3 class="text-primary mb-3">₹<?= number_format($product['price'], 2); ?></h3>

    <?php if (isset($product['stock']) && (int)$product['stock'] <= 0): ?>
      <p class="text-danger fw-semibold">Out of stock</p>
    <?php endif; ?>

    <p><?= nl2br(htmlspecialchars($product['description'])); ?></p>

    <form method="post" class="mt-3 mb-3">
      <div class="d-flex align-items-center mb-3">
        <label class="me-2">Quantity:</label>
        <input type="number" name="quantity" min="1" value="1" class="form-control w-auto">
      </div>

      <button type="submit" class="btn btn-primary btn-lg me-2"
        <?php if (isset($product['stock']) && (int)$product['stock'] <= 0): ?>
          disabled
        <?php endif; ?>
      >
        Add to Cart
      </button>

      <?php
        // WhatsApp link specific to this product
        global $whatsapp_number, $site_name;
        if (!empty($whatsapp_number)):
            $waText = urlencode(
                "Hi $site_name, I have a question about product: {$product['name']} (ID: {$product['id']})."
            );
            $waUrl = "https://wa.me/$whatsapp_number?text=$waText";
      ?>
        <a href="<?= $waUrl; ?>" target="_blank" rel="noopener"
           class="btn btn-outline-success btn-lg">
          Chat on WhatsApp
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
