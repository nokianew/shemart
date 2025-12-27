<?php
require_once __DIR__ . '/functions.php';

$slug = $_GET['slug'] ?? '';
$products = getProductsByCategorySlug($slug);

if (!$products) {
    $page_title = "Category not found";
} else {
    $page_title = $products[0]['category_name'];
}

include __DIR__ . '/partials/header.php';
?>

<h1 class="mb-4"><?= htmlspecialchars($page_title); ?></h1>

<?php if (!$products): ?>
  <p>No products in this category yet.</p>
<?php else: ?>
  <div class="row">
    <?php foreach ($products as $product): ?>
      <div class="col-6 col-md-3 mb-4">
        <div class="card h-100">
          <img src="assets/images/<?= htmlspecialchars($product['image'] ?? 'placeholder.jpg'); ?>" 
     class="card-img-top" alt="<?= htmlspecialchars($product['name']); ?>">

          <div class="card-body d-flex flex-column">
            <h6 class="card-title mb-1"><?= htmlspecialchars($product['name']); ?></h6>
            <strong class="mb-2">₹<?= number_format($product['price'], 2); ?></strong>
            <a href="product.php?id=<?= $product['id']; ?>" class="btn btn-sm btn-primary mt-auto">
              View
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
