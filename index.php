<?php
require_once __DIR__ . '/functions.php';

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $products = searchProducts($q, 20);
    $page_title = "Search: " . $q;
} else {
    $products = getProducts(8);
    $page_title = "Shop the latest picks";
}

include __DIR__ . '/partials/header.php';
?>

<!-- Hero banner -->
<section class="mb-4">
  <div class="p-4 p-md-5 rounded-3" style="background:#ffe6f2;">
    <div class="row align-items-center">
      <div class="col-md-7">
        <h2 class="mb-2">Welcome to SheMart</h2>
        <p class="mb-0 text-muted">
          Discover premium beauty, fashion, grooming and accessories curated especially for her.
        </p>
      </div>
    </div>
  </div>
</section>
<?php
// Ensure $pdo is available (if not require config.php)
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}

// Fetch visible categories (adjust column names to match your DB)
$catStmt = $pdo->prepare("SELECT id, name, slug, icon, description FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Shop by Category -->
<section class="shop-by-category my-5 container">
  <h3>Shop by Category</h3>
  <div class="row mt-3">
    <?php if ($categories): ?>
      <?php foreach ($categories as $cat): ?>
        <div class="col-md-3 mb-3">
          <a href="category.php?slug=<?= htmlspecialchars($cat['slug']) ?>" class="card p-3 text-center text-decoration-none h-100">
            <div class="mb-2">
              <?php if (!empty($cat['icon'])): ?>
                <img src="assets/images/<?=htmlspecialchars($cat['icon']) ?>" 
     alt="<?=htmlspecialchars($cat['name']) ?>" 
     style="height:96px; margin-bottom:10px;">


              <?php else: ?>
                <span style="font-size:28px;">📦</span>
              <?php endif; ?>
            </div>
            <h5 class="mb-1"><?= htmlspecialchars($cat['name']) ?></h5>
            <small class="text-muted d-block"><?= htmlspecialchars($cat['description'] ?? '') ?></small>
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <p class="text-muted">No categories available.</p>
      </div>
    <?php endif; ?>
  </div>
</section>


<!-- Products / Search results -->
<section>
  <h4 class="mb-3">
    <?= $q !== '' ? 'Search results' : 'Featured Products'; ?>
  </h4>

  <?php if (!$products): ?>
    <p class="text-muted">No products found.</p>
  <?php else: ?>
    <div class="row mb-4">
      <?php foreach ($products as $product): ?>
        <div class="col-6 col-md-3 mb-4">
          <div class="card h-100">
            <img src="assets/images/<?= htmlspecialchars($product['image'] ?? 'placeholder.jpg'); ?>" 
     class="card-img-top" alt="<?= htmlspecialchars($product['name']); ?>">

            <div class="card-body d-flex flex-column">
              <small class="text-muted mb-1"><?= htmlspecialchars($product['category_name']); ?></small>
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
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
