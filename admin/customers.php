<?php
// admin/customers.php

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

requirePermission('customers.view');

require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

$page_title = "Customers";
require_once __DIR__ . '/_admin_header.php';

function esc($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// -------------------------
// Search
// -------------------------
$search = trim($_GET['q'] ?? '');
$params = [];
$where = '';

if ($search !== '') {
    $where = "WHERE u.name LIKE :q OR u.email LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

// -------------------------
// Fetch customers with FINAL business logic
// -------------------------
$sql = "
SELECT 
    u.id,
    u.name,
    u.email,
    u.phone,
    u.created_at,

    /* Orders = only DELIVERED */
    COUNT(
        CASE 
            WHEN o.order_status = 'DELIVERED'
            THEN 1
        END
    ) AS total_orders,

    /* Revenue = only DELIVERED */
    COALESCE(SUM(
        CASE 
            WHEN o.order_status = 'DELIVERED'
            THEN o.total_amount
            ELSE 0
        END
    ), 0) AS total_spent

FROM users u
LEFT JOIN orders o ON o.user_id = u.id
$where
GROUP BY u.id
ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Customers</h2>
    <p class="text-muted">View registered customers and their order history</p>

    <!-- Search -->
    <form method="get" class="mb-3">
        <div class="input-group" style="max-width: 380px;">
            <input
                type="text"
                name="q"
                class="form-control"
                placeholder="Search customer name or email"
                value="<?= esc($search) ?>"
            >
            <button class="btn btn-outline-primary" type="submit">
                Search
            </button>
            <?php if ($search !== ''): ?>
                <a href="customers.php" class="btn btn-outline-secondary">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($search !== ''): ?>
        <p class="text-muted">
            Showing <?= count($customers) ?> result(s) for “<?= esc($search) ?>”
        </p>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Registered</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$customers): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                No customers found
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>#<?= (int)$c['id'] ?></td>

                            <td>
                                <a href="customer_view.php?id=<?= (int)$c['id'] ?>">
                                    <?= esc($c['name']) ?>
                                </a>
                            </td>

                            <td><?= esc($c['email']) ?></td>

                            <td>
                                <?= !empty($c['phone'])
                                    ? esc($c['phone'])
                                    : '<span class="text-muted">—</span>' ?>
                            </td>

                            <td><?= (int)$c['total_orders'] ?></td>

                            <td>₹<?= number_format($c['total_spent'], 2) ?></td>

                            <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>

                            <td>
                                <a href="customer_view.php?id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-primary">
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
