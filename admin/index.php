<?php
require_once __DIR__ . '/../config.php';

?>
<?php
// admin/index.php
require_once __DIR__ . '/../functions.php';

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!empty($_SESSION['admin_id'])) return;
        if (function_exists('requireUser')) return adminRequireUser();

        exit;
    }
}

requireAdmin();

$admin = [
    'username' => $_SESSION['admin_username'] ?? 'admin',
];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard - SheMart</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f4f6f8;color:#222}
    header{background:#fff;padding:16px 24px;border-bottom:1px solid #e6e9ee;display:flex;justify-content:space-between;align-items:center}
    .container{max-width:1100px;margin:28px auto;padding:0 18px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
    .card{background:#fff;padding:18px;border-radius:8px;box-shadow:0 6px 18px rgba(15,20,25,0.04)}
    a.button{display:inline-block;padding:8px 12px;border-radius:6px;background:#0b74de;color:#fff;text-decoration:none}
    nav a{margin-right:12px;color:#0b74de;text-decoration:none}
  </style>
</head>
<body>
  <header>
    <div>
      <strong>SheMart Admin</strong> — welcome, <?=htmlspecialchars($admin['username'])?>
    </div>
    <div>
      <nav>
        <a href="<?php echo $adminBase . '/orders.php'; ?>">Orders</a>
        <a href="<?php echo $adminBase . '/products.php'; ?>">Products</a>
        <a href="<?php echo $adminBase . '/categories.php'; ?>">Categories</a>
        <a href="<?php echo $adminBase . '/users.php'; ?>">Customers</a>
        <a href="<?php echo $adminBase . '/logout.php'; ?>" style="color:#c33">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container">
    <h1>Dashboard</h1>

    <div class="grid">
      <div class="card">
        <h3>Quick actions</h3>
        <p>
          <a class="button" href="<?php echo $adminBase . '/orders.php'; ?>">View Orders</a>
          <a class="button" href="<?php echo $adminBase . '/products.php'; ?>">Manage Products</a>
        </p>
      </div>

      <div class="card">
        <h3>Recent orders</h3>
        <?php
        try {
          $pdo = $GLOBALS['pdo'] ?? null;
          if ($pdo) {
            $stmt = $pdo->query("SELECT id, customer_name, total_amount, order_status, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
              echo "<ul>";
              foreach($rows as $r) {
                echo "<li>#".htmlspecialchars($r['id'])." — ".htmlspecialchars($r['customer_name'])." (₹".htmlspecialchars($r['total_amount']).") — ".htmlspecialchars($r['order_status'])."</li>";
              }
              echo "</ul>";
            } else {
              echo "<p>No recent orders.</p>";
            }
          } else {
            echo "<p style='color:#888'>Database not available.</p>";
          }
        } catch (Exception $e) {
          echo "<p style='color:#888'>Could not load orders.</p>";
        }
        ?>
      </div>

      <div class="card">
        <h3>Site health</h3>
        <p>Server time: <?=date('Y-m-d H:i:s')?></p>
        <p>PHP version: <?=phpversion()?></p>
      </div>
    </div>
  </main>
</body>
</html>
