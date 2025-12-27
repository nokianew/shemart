<?php
// thankyou.php
$order_id = $_GET['order_id'] ?? '';
$whatsapp_url = $_GET['whatsapp_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Placed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fafafa;
            padding: 40px;
            text-align: center;
        }
        .box {
            background: #fff;
            padding: 30px;
            max-width: 380px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 0 10px #ddd;
        }
        h2 { color: #28a745; margin-bottom: 10px; }
        p { font-size: 16px; }
        a.btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 20px;
            background: #28a745;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 15px;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Thank You!</h2>
    <p>Your order <strong>#<?php echo htmlspecialchars($order_id); ?></strong> has been placed successfully.</p>
    <p>We will notify you soon about your delivery details.</p>

    <a class="btn" href="index.php">Continue Shopping</a>
</div>

<?php if ($whatsapp_url): ?>
<script>
// Automatically open WhatsApp for admin notification
window.location.href = "<?php echo $whatsapp_url; ?>";
</script>
<?php endif; ?>

</body>
</html>
