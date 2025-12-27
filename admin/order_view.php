<?php
// admin/order_view.php

require_once __DIR__ . '/../includes/functions.php';
adminRequireUser();
require_once __DIR__ . '/../functions.php';

// Orders access only
requirePermission('orders.view');

$page_title = "View Order";
require_once __DIR__ . '/_admin_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: orders.php');
    exit;
}

// -----------------------------
// ORDER STATUS FLOW (LOCKED)
// -----------------------------
$statusFlow = [
    'placed'     => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped'    => ['delivered'],
    'delivered'  => [],
    'cancelled'  => [],
];

// -----------------------------
// HANDLE STATUS UPDATE
// -----------------------------
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('orders.update_status');

    $newStatus = strtolower(trim($_POST['status'] ?? ''));
    $trackingCode = trim($_POST['tracking_code'] ?? '');

    // Fetch current status
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $currentStatus = strtolower($stmt->fetchColumn());

    if (!$currentStatus) {
        $error_msg = "Invalid order.";
    } else {
        $allowed = $statusFlow[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            $error_msg = "Invalid status transition.";
        } else {
            // Update order
            $stmt = $pdo->prepare("
                UPDATE orders
                SET status = ?, tracking_code = ?
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $trackingCode, $id]);

            $success_msg = "Order updated successfully.";
            $currentStatus = $newStatus;
        }
    }
}

// -----------------------------
// FETCH ORDER
// -----------------------------
$stmt = $pdo->prepare("
    SELECT o.*, u.name AS user_name, u.email AS user_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<p>Order not found.</p>";
    require_once __DIR__ . '/_admin_footer.php';
    exit;
}

$currentStatus = strtolower($order['status']);

// -----------------------------
// FETCH ITEMS
// -----------------------------
$stmtItems = $pdo->prepare("
    SELECT oi.*, p.name AS product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// PERMISSIONS
// -----------------------------
$canUpdateStatus = can('orders.update_status');

// Allowed next statuses
$allowedNextStatuses = [];
if ($canUpdateStatus && isset($statusFlow[$currentStatus])) {
    $allowedNextStatuses = $statusFlow[$currentStatus];
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>Order #<?= (int)$order['id']; ?></h1>
  <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
</div>

<?php if ($success_msg): ?>
  <div class="alert alert-success"><?= esc($success_msg); ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
  <div class="alert alert-danger"><?= esc($error_msg); ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-md-6 mb-4">

    <div class="card mb-3">
      <div class="card-header">Order Info</div>
      <div class="card-body">
        <p><strong>Date:</strong> <?= esc($order['created_at']); ?></p>
        <p><strong>Total:</strong> ₹<?= number_format($order['total_amount'], 2); ?></p>
        <p><strong>Status:</strong> <?= ucfirst($currentStatus); ?></p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Customer Info</div>
      <div class="card-body">
        <p><strong>Name:</strong> <?= esc($order['customer_name']); ?></p>
        <p><strong>Email:</strong> <?= esc($order['customer_email']); ?></p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Shipping Address</div>
      <div class="card-body">
        <pre class="mb-0"><?= esc($order['shipping_address'] ?? ''); ?></pre>
      </div>
    </div>

  </div>

  <div class="col-md-6 mb-4">

    <div class="card mb-3">
      <div class="card-header">Order Status</div>
      <div class="card-body">

        <form method="post">
          <div class="mb-3">
            <label class="form-label">Change Status</label>

            <select name="status" class="form-select"
              <?= (!$canUpdateStatus || empty($allowedNextStatuses)) ? 'disabled' : ''; ?>>

              <option selected value="<?= esc($currentStatus); ?>">
                <?= ucfirst($currentStatus); ?>
              </option>

              <?php foreach ($allowedNextStatuses as $st): ?>
                <option value="<?= esc($st); ?>">
                  <?= ucfirst($st); ?>
                </option>
              <?php endforeach; ?>

            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Tracking Code</label>
            <input type="text"
                   name="tracking_code"
                   class="form-control"
                   value="<?= esc($order['tracking_code'] ?? ''); ?>">
          </div>

          <button type="submit"
                  class="btn btn-primary"
                  <?= (!$canUpdateStatus || empty($allowedNextStatuses)) ? 'disabled' : ''; ?>>
            Save
          </button>
        </form>

      </div>
    </div>

    <div class="card">
      <div class="card-header">Order Items</div>
      <div class="card-body">
        <?php if (empty($items)): ?>
          <p class="text-muted">No items found.</p>
        <?php else: ?>
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?= esc($it['product_name'] ?? '#'.$it['product_id']); ?></td>
                  <td><?= (int)$it['quantity']; ?></td>
                  <td>₹<?= number_format($it['price'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
