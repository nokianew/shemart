<?php
require_once __DIR__ . '/../functions.php';
if (!isset($page_title)) { $page_title = $site_name; }
$categories = getCategories();
$user = currentUser();
$q = $_GET['q'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($site_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php"><?= htmlspecialchars($site_name) ?></a>

    <!-- Search (mobile on top) -->
    <form class="d-lg-none d-flex me-2" method="get" action="index.php">
      <input class="form-control form-control-sm me-2" type="search" name="q"
             placeholder="Search products..." value="<?= htmlspecialchars($q); ?>">
      <button class="btn btn-sm btn-outline-primary">Search</button>
    </form>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($categories as $cat): ?>
          <li class="nav-item">
            <a class="nav-link" href="category.php?slug=<?= urlencode($cat['slug']) ?>">
              <?= htmlspecialchars($cat['name']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Desktop search -->
      <form class="d-none d-lg-flex me-3" method="get" action="index.php">
        <input class="form-control form-control-sm me-2" type="search" name="q"
               placeholder="Search products..." value="<?= htmlspecialchars($q); ?>">
        <button class="btn btn-sm btn-outline-primary">Search</button>
      </form>

                 <!-- User / Auth links (single command) -->
            <div class="me-3 small d-flex align-items-center">
        <?php if ($user): ?>
          <!-- My Account button -->
          <a href="account.php" class="btn btn-sm btn-outline-primary me-2">
            My Account
          </a>

    
          <!-- Greeting & logout -->
          <span class="me-2">Hi, <strong><?= htmlspecialchars($user['name']); ?></strong></span>
          <a href="logout.php" class="text-decoration-none">Logout</a>
        <?php else: ?>
          <a href="auth.php" class="btn btn-sm btn-outline-primary">
            Login / Sign up
          </a>
        <?php endif; ?>
      </div>




      <a href="cart.php" class="btn btn-outline-primary">
        Cart
        <?php
          $cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
          if ($cart_count > 0): ?>
            <span class="badge bg-primary"><?= $cart_count; ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>
</nav>

<main class="py-4">
  <div class="container">
