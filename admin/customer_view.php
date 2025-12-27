<?php
// admin/customer_view.php

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

requirePermission('customers.view');

require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

function esc($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: customers.php');
    exit;
}

// Fetch customer
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Fetch order stats
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        MAX(created_at) AS last_order_date
    FROM orders
    WHERE user_id = ?
");
$statsStmt->execute([$id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Fetch latest phone number from orders (shipping phone)


// Fetch orders
$ordersStmt = $pdo->prepare("
    SELECT 
        id,
        total_amount,
        order_status,
        created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$ordersStmt->execute([$id]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Customer Details";
require_once __DIR__ . '/_admin_header.php';
?>

<div class="container mt-4">
    <a href="customers.php" class="btn btn-sm btn-outline-secondary mb-3">
        ← Back to Customers
    </a>

    <!-- Customer Info -->
    <div class="card mb-4">
        <div class="card-body">
            <h4><?= esc($customer['name']) ?></h4>

            <p class="mb-1">
                <strong>Email:</strong> <?= esc($customer['email']) ?>
            </p>

            <p class="mb-1">
                <strong>Phone:</strong>
                <?= !empty($customer['phone']) ? esc($customer['phone']) : '<span class="text-muted">—</span>' ?>
            </p>


            <p class="mb-1">
                <strong>Customer ID:</strong> #<?= (int)$customer['id'] ?>
            </p>

            <p class="text-muted">
                Registered on <?= date('d M Y', strtotime($customer['created_at'])) ?>
            </p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5><?= (int)$stats['total_orders'] ?></h5>
                    <small>Total Orders</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5>₹<?= number_format($stats['total_spent'], 2) ?></h5>
                    <small>Total Spent</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5>
                        <?= $stats['last_order_date']
                            ? date('d M Y', strtotime($stats['last_order_date']))
                            : '—' ?>
                    </h5>
                    <small>Last Order</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders -->
    <div class="card">
        <div class="card-body">
            <h5>Orders</h5>

            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">
                                No orders placed yet
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>#<?= (int)$o['id'] ?></td>
                            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                            <td>₹<?= number_format($o['total_amount'], 2) ?></td>
                            <td><?= esc($o['order_status']) ?></td>
                            <td>
                                <a href="order_view.php?id=<?= (int)$o['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
