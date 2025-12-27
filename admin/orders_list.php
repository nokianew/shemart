<?php
// --------------------------------------
// Orders List — RBAC Protected
// --------------------------------------

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/permissions.php';

// 🔐 View orders permission
requirePermission('orders.view');

require_once __DIR__ . '/../includes/functions.php';

if (!isset($pdo) && function_exists('getDB')) {
    $pdo = getDB();
}


// Optional: require a role (future use)
// requireRole('admin'); // uncomment when role-based permissions implemented

// orders_list.php - Final for your DB schema (Sunny K / Nova)
// ----------------------------------------------------------
// Expects orders table columns as in your screenshot (total_amount, shipping_* fields, etc.)
// PDO + prepared statements. Minimal CSS to match admin look.

// DB config - change if needed
$db_host = 'localhost';
$db_name = 'womenshop';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Helpers
function statusBadge($status) {
    $map = [
        'PLACED' => 'badge-pending',
        'Pending' => 'badge-pending',
        'Placed' => 'badge-pending',
        'PROCESSING' => 'badge-accepted',
        'Processing' => 'badge-accepted',
        'SHIPPED' => 'badge-completed',
        'Shipped' => 'badge-completed',
        'DELIVERED' => 'badge-completed',
        'Delivered' => 'badge-completed',
        'CANCELLED' => 'badge-rejected',
        'Cancelled' => 'badge-rejected'
    ];
    $cls = $map[$status] ?? 'badge-default';
    return "<span class='status-badge {$cls}'>" . htmlspecialchars($status) . "</span>";
}

// Inputs / Filters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

$search = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? 'all');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Build WHERE
$where = [];
$params = [];

// Search on id, customer_name, customer_email, users.phone
if ($search !== '') {
    $where[] = "(CAST(o.id AS CHAR) LIKE :s OR o.customer_name LIKE :s OR o.customer_email LIKE :s OR u.phone LIKE :s)";
    $params[':s'] = "%$search%";
}

// Status filter
if ($status !== '' && $status !== 'all') {
    $where[] = "o.order_status = :status";
    $params[':status'] = $status;
}

// Date range
if ($date_from !== '' && $date_to !== '') {
    // expecting YYYY-MM-DD
    $where[] = "DATE(o.created_at) BETWEEN :dfrom AND :dto";
    $params[':dfrom'] = $date_from;
    $params[':dto'] = $date_to;
}

$whereSql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// Pagination - count
$countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereSql";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$offset = ($page - 1) * $perPage;

// Main query - select the fields your table has
$sql = "
SELECT
  o.id,
  o.customer_name,
  o.customer_email,
  o.total_amount,
  o.payment_method,
  o.payment_status,
  o.order_status,
  o.created_at,
  o.shipping_name,
  o.shipping_phone,
  o.shipping_address_line1,
  o.shipping_address_line2,
  o.shipping_city,
  o.shipping_state,
  o.shipping_postal_code,
  o.shipping_country,
  o.user_id,
  u.phone AS user_phone
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
$whereSql
ORDER BY o.created_at DESC
LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats (simple)
$stat = $pdo->query("
SELECT
  COUNT(*) AS total_orders,
  SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_orders,
  SUM(CASE WHEN order_status='DELIVERED' OR order_status='Delivered' THEN 1 ELSE 0 END) AS completed_orders,
  SUM(CASE WHEN order_status='CANCELLED' OR order_status='Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
FROM orders
")->fetch(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin — Orders</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:Inter,system-ui; background:#f7f8fa; margin:0}
.container{max-width:1200px;margin:28px auto;padding:22px}
.header{display:flex;justify-content:space-between;align-items:center}
.page-title{font-size:26px;font-weight:700}
.small-muted{color:#6b7280}
.top-cards{display:flex;gap:12px;margin:18px 0}
.card{flex:1;background:#fff;padding:14px;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,0.04)}
.card small{color:#888}
.card .num{font-size:20px;font-weight:700;margin-top:6px}
.filters{display:flex;gap:8px;align-items:center;margin:12px 0}
input,select{padding:8px;border-radius:8px;border:1px solid #e6e9ee}
.btn{background:#2563eb;color:#fff;padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
.table-card{background:#fff;padding:12px;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,0.04)}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 10px;border-bottom:1px solid #f0f1f4}
th{color:#6b7280;text-align:left}
.status-badge{padding:6px 10px;border-radius:999px;font-weight:600}
.badge-pending{background:#fff7e6;color:#b26b00;border:1px solid #ffe8c9}
.badge-accepted{background:#ecfdf5;color:#064e3b;border:1px solid #bbf7d0}
.badge-completed{background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe}
.badge-rejected{background:#fff1f2;color:#9f1239;border:1px solid #fecaca}
.avatar{width:36px;height:36px;border-radius:50%;background:#e6eef6;display:inline-flex;align-items:center;justify-content:center;color:#0b516e;font-weight:700}
.action-btn{padding:6px 10px;border-radius:8px;border:1px solid #e6e9ee;background:#fff;cursor:pointer}
.pagination{display:flex;gap:6px;justify-content:flex-end;margin-top:12px}
.page-item{padding:6px 10px;border-radius:8px;border:1px solid #e6e9ee;background:#fff}
.small-note{font-size:13px;color:#6b7280}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div>
      <div class="page-title">Orders</div>
      <div class="small-muted">Manage all orders placed in your SheMart store.</div>
    </div>
    <div><a href="dashboard.php" class="btn">View Dashboard</a></div>
  </div>

  <div class="top-cards">
    <div class="card"><small>Total Orders</small><div class="num"><?= htmlspecialchars($stat['total_orders'] ?? 0) ?></div></div>
    <div class="card"><small>New (7 days)</small><div class="num"><?= htmlspecialchars($stat['new_orders'] ?? 0) ?></div></div>
    <div class="card"><small>Completed</small><div class="num"><?= htmlspecialchars($stat['completed_orders'] ?? 0) ?></div></div>
    <div class="card"><small>Cancelled</small><div class="num"><?= htmlspecialchars($stat['cancelled_orders'] ?? 0) ?></div></div>
  </div>

  <form class="filters" method="get">
    <input type="text" name="q" placeholder="Search order id, customer, email, phone..." value="<?= htmlspecialchars($search) ?>">
    <select name="status">
      <option value="all">All statuses</option>
      <option value="PLACED" <?= $status==='PLACED' ? 'selected' : '' ?>>Placed</option>
      <option value="PROCESSING" <?= $status==='PROCESSING' ? 'selected' : '' ?>>Processing</option>
      <option value="SHIPPED" <?= $status==='SHIPPED' ? 'selected' : '' ?>>Shipped</option>
      <option value="DELIVERED" <?= $status==='DELIVERED' ? 'selected' : '' ?>>Delivered</option>
      <option value="CANCELLED" <?= $status==='CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
    </select>
    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
    <button class="btn" type="submit">Filter</button>
  </form>

  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Items</th>
          <th>Total</th>
          <th>Payment</th>
          <th>Shipping</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
<?php if (!$orders): ?>
        <tr><td colspan="8" style="text-align:center;padding:24px">No orders found</td></tr>
<?php else: foreach ($orders as $o):
    // prepare small fields
    $displayOrder = '#' . ($o['id'] ?? '');
    $custName = $o['customer_name'] ?? 'Guest';
    $custEmail = $o['customer_email'] ?? '';
    // prefer users.phone if present else shipping_phone
    $custPhone = $o['user_phone'] ?? ($o['shipping_phone'] ?? '');
    $total = isset($o['total_amount']) ? number_format($o['total_amount'],2) : '-';
    $paymentInfo = trim(($o['payment_method'] ?? '') . ' ' . (!empty($o['payment_status']) ? ' (' . $o['payment_status'] . ')' : ''));
    $shipping = trim(($o['shipping_name'] ?? '') . "\n" . ($o['shipping_city'] ?? '') . (!empty($o['shipping_postal_code']) ? ' ' . $o['shipping_postal_code'] : ''));
?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($displayOrder) ?></strong><br>
            <div class="small-note"><?= !empty($o['created_at']) ? date("d/m/Y, h:i a", strtotime($o['created_at'])) : '' ?></div>
          </td>
          <td>
            <div style="display:flex;gap:10px;align-items:center">
              <div class="avatar"><?= strtoupper(substr($custName,0,1)) ?></div>
              <div>
                <div style="font-weight:600"><?= htmlspecialchars($custName) ?></div>
                <div class="small-note"><?= htmlspecialchars($custEmail) ?></div>
                <div class="small-note"><?= htmlspecialchars($custPhone) ?></div>
              </div>
            </div>
          </td>

          <td class="small-note"> <!-- items not joined here; placeholder -->
            view order
          </td>

          <td>₹ <?= htmlspecialchars($total) ?></td>
          <td class="small-note"><?= htmlspecialchars($paymentInfo) ?></td>
          <td class="small-note"><?= nl2br(htmlspecialchars($shipping)) ?></td>
          <td><?= statusBadge($o['order_status'] ?? '') ?></td>
          <td><a class="action-btn" href="order_view.php?id=<?= intval($o['id'] ?? 0) ?>">View</a></td>
        </tr>
<?php endforeach; endif; ?>
      </tbody>
    </table>

    <div class="pagination">
<?php
$start = max(1, $page - 2);
$end = min($totalPages, $page + 2);
for ($i = $start; $i <= $end; $i++): ?>
      <a class="page-item" style="<?= $i==$page ? 'background:#10b981;color:#fff;border-color:#10b981' : '' ?>" href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
<?php endfor; ?>
<?php if ($page < $totalPages): ?>
      <a class="page-item" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">Next →</a>
<?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
