<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../db.php';

requireUser();
$user = currentUser();
$uid = (int)$user['id'];

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order
$sql = "SELECT id, user_id, total_amount, status, created_at, shipping_address
        FROM orders
        WHERE id = :id
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Prevent users from viewing someone else's order
if (!$order || (int)$order['user_id'] !== $uid) {
    echo "Order not found.";
    exit;
}

// Get items
$sqlItems = "SELECT oi.product_id, oi.qty, oi.price, p.title 
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :oid";
$stmt = $pdo->prepare($sqlItems);
$stmt->execute([':oid' => $order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmt_price($v) {
    return number_format((float)$v, 2);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order #<?php echo $order['id']; ?></title>
</head>
<body>

<h1>Order #<?php echo $order['id']; ?></h1>
<p><strong>Date:</strong> <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></p>
<p><strong>Status:</strong> <?php echo $order['status']; ?></p>
<p><strong>Total:</strong> <?php echo fmt_price($order['total_amount']); ?></p>

<?php if (!empty($order['shipping_address'])): ?>
    <p><strong>Shipping Address:</strong><br><?php echo nl2br($order['shipping_address']); ?></p>
<?php endif; ?>

<h2>Items</h2>
<table border="1" cellpadding="8">
    <tr>
        <th>Product</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Subtotal</th>
    </tr>

    <?php foreach ($items as $item): ?>
        <tr>
            <td><?php echo $item['title'] ?: 'Product #' . $item['product_id']; ?></td>
            <td><?php echo $item['qty']; ?></td>
            <td><?php echo fmt_price($item['price']); ?></td>
            <td><?php echo fmt_price($item['qty'] * $item['price']); ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<br>
<a href="orders.php">← Back to Orders</a>

</body>
</html>
