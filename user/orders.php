<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../db.php';

requireUser();
$user = currentUser();
$uid = (int)$user['id'];

// Fetch orders
$sql = "SELECT id, user_id, total_amount, status, created_at
        FROM orders
        WHERE user_id = :uid
        ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $uid]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmt_price($v) {
    return number_format((float)$v, 2);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Orders</title>
</head>
<body>

<h1>My Orders</h1>

<?php if (empty($orders)): ?>
    <p>No orders found.</p>
<?php else: ?>
<table border="1" cellpadding="8">
    <tr>
        <th>Order ID</th>
        <th>Date</th>
        <th>Total</th>
        <th>Status</th>
        <th>View</th>
    </tr>

    <?php foreach ($orders as $o): ?>
        <tr>
            <td>#<?php echo $o['id']; ?></td>
            <td><?php echo date('d M Y H:i', strtotime($o['created_at'])); ?></td>
            <td><?php echo fmt_price($o['total_amount']); ?></td>
            <td><?php echo $o['status']; ?></td>
            <td>
                <a href="order_view.php?id=<?php echo $o['id']; ?>">View</a>
            </td>
        </tr>
    <?php endforeach; ?>

</table>
<?php endif; ?>

</body>
</html>
