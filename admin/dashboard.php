<?php
// --------------------------------------
// Admin Dashboard — RBAC Aware
// --------------------------------------

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

// Dashboard permission (ALL roles must have this)
requirePermission('dashboard.view');

$pdo = getDB();
$page_title = "Dashboard";
require_once __DIR__ . '/_admin_header.php';

$role = $_SESSION['admin']['role'] ?? 'unknown';

/* ---------------------------------------------------
   Helpers
--------------------------------------------------- */
function countTable($pdo, $table) {
    try {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return 0;
        return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---------------------------------------------------
   Counts
--------------------------------------------------- */
$totalProducts   = countTable($pdo, 'products');
$totalCategories = countTable($pdo, 'categories');
$totalOrders     = countTable($pdo, 'orders');
$totalUsers      = countTable($pdo, 'users');

/* ---------------------------------------------------
   Recent Orders
--------------------------------------------------- */
$latestOrders = [];
if (can('orders.view')) {
    try {
        $latestOrders = $pdo->query("
            SELECT id, customer_name, total_amount, tracking_status
            FROM orders
            ORDER BY created_at DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
?>

<div class="container-fluid">

<!-- ================= PAGE HEADER ================= -->
<div class="row mb-4">
  <div class="col">
    <h1 class="h3 mb-0">Dashboard</h1>
    <p class="text-muted mb-0">
      Logged in as <strong><?= htmlspecialchars($role) ?></strong>
    </p>
  </div>
</div>

<!-- ================= SYSTEM OVERVIEW (SUPER ONLY) ================= -->
<?php if ($role === 'super'): ?>
<h4 class="mb-3">System Overview</h4>

<div class="row g-3 mb-4">

  <div class="col-md-3">
    <a href="admin_management.php"
       class="card h-100 text-decoration-none border-primary">
      <div class="card-body">
        <h6 class="mb-1">Admin Management</h6>
        <p class="text-muted small mb-0">Create & manage admins</p>
      </div>
    </a>
  </div>

  <div class="col-md-3">
    <a href="orders.php"
       class="card h-100 text-decoration-none border-success">
      <div class="card-body">
        <h6 class="mb-1">Orders</h6>
        <p class="text-muted small mb-0">View & process orders</p>
      </div>
    </a>
  </div>

  <div class="col-md-3">
    <a href="products.php"
       class="card h-100 text-decoration-none border-warning">
      <div class="card-body">
        <h6 class="mb-1">Products</h6>
        <p class="text-muted small mb-0">Manage inventory & pricing</p>
      </div>
    </a>
  </div>

  <div class="col-md-3">
    <a href="categories.php"
       class="card h-100 text-decoration-none border-info">
      <div class="card-body">
        <h6 class="mb-1">Categories</h6>
        <p class="text-muted small mb-0">Organize catalog</p>
      </div>
    </a>
  </div>

</div>
<?php endif; ?>

<!-- ================= METRICS ================= -->
<div class="row g-3 mb-4">

  <?php if (can('products.view')): ?>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <h6>Products</h6>
        <h3><?= $totalProducts ?></h3>
        <a href="products.php" class="small">View</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (can('categories.view')): ?>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <h6>Categories</h6>
        <h3><?= $totalCategories ?></h3>
        <a href="categories.php" class="small">View</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (can('orders.view')): ?>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <h6>Orders</h6>
        <h3><?= $totalOrders ?></h3>
        <a href="orders.php" class="small">View</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (can('users.view')): ?>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <h6>Customers</h6>
        <h3><?= $totalUsers ?></h3>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ================= RECENT ORDERS ================= -->
<?php if (can('orders.view')): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white">
    <strong>Recent Orders</strong>
  </div>
  <div class="card-body">
    <?php if (empty($latestOrders)): ?>
      <p class="text-muted mb-0">No recent orders.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($latestOrders as $o): ?>
            <tr>
              <td>#<?= (int)$o['id'] ?></td>
              <td><?= htmlspecialchars($o['customer_name']) ?></td>
              <td>₹<?= number_format($o['total_amount'], 2) ?></td>
              <td><?= htmlspecialchars($o['tracking_status']) ?></td>
              <td>
                <a href="orders.php?id=<?= (int)$o['id'] ?>"
                   class="btn btn-sm btn-outline-primary">View</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

</div><!-- container -->
