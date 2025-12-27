<?php
// place_order.php — FINAL FIXED VERSION (name/email guaranteed)

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/* ----------------------------------------
   Detect JSON / AJAX
---------------------------------------- */
function isJsonRequest(): bool {
    return (
        stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ||
        stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    );
}

/* ----------------------------------------
   WhatsApp helper
---------------------------------------- */
if (!function_exists('buildWhatsAppAdminUrl')) {
    function buildWhatsAppAdminUrl(int $orderId, float $amount, string $name): string {
        $adminPhone = '916260096745';
        $msg = "New Order Received\n"
             . "Order ID: {$orderId}\n"
             . "Customer: {$name}\n"
             . "Total: ₹" . number_format($amount, 2);
        return "https://wa.me/{$adminPhone}?text=" . urlencode($msg);
    }
}

/* ----------------------------------------
   Clear cart
---------------------------------------- */
function clearCart(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    unset($_SESSION['cart'], $_SESSION['cart_count'], $_SESSION['cart_total']);
    setcookie('cart', '', time() - 3600, '/');
    setcookie('cart_count', '', time() - 3600, '/');
    setcookie('cart_total', '', time() - 3600, '/');
}

/* ----------------------------------------
   Read input
---------------------------------------- */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) $data = $_POST;

$cart          = $data['cart'] ?? [];
$paymentMethod = strtoupper($data['payment_method'] ?? 'COD');
$address       = $data['address'] ?? [];
$shipping      = $data['shipping'] ?? [];
$shippingSame  = !empty($data['shipping_same_as_billing']) ? 1 : 0;

/* ----------------------------------------
   GUARANTEED customer identity
---------------------------------------- */
$customerName =
    trim(
        $data['customer_name']
        ?? $address['name']
        ?? $shipping['name']
        ?? 'Guest'
    );

if ($customerName === '') {
    $customerName = 'Guest';
}

$customerEmail =
    trim(
        $data['customer_email']
        ?? $address['email']
        ?? ''
    );

$customerPhone =
    $address['phone']
    ?? $shipping['phone']
    ?? '';

/* ----------------------------------------
   Validation
---------------------------------------- */
if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart is empty']);
    exit;
}

if (!in_array($paymentMethod, ['COD', 'RAZORPAY'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment method']);
    exit;
}

try {
    $pdo->beginTransaction();

    /* ----------------------------------------
       Validate products & totals
    ---------------------------------------- */
    $stmt = $pdo->prepare(
        "SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE"
    );

    $items = [];
    $subtotal = 0;

    foreach ($cart as $c) {
        $pid = (int)$c['product_id'];
        $qty = max(1, (int)$c['quantity']);

        $stmt->execute([$pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) throw new Exception("Product not found");

        if ($paymentMethod === 'COD' && $p['stock'] < $qty) {
            throw new Exception("Insufficient stock for {$p['name']}");
        }

        $subtotal += $p['price'] * $qty;
        $items[] = [
            'product_id' => $pid,
            'quantity'   => $qty,
            'price'      => $p['price']
        ];
    }

    $deliveryCharge = ($subtotal >= 1000) ? 0 : 50;
    $totalAmount = round($subtotal + $deliveryCharge, 2);

    /* ----------------------------------------
       Save address
    ---------------------------------------- */
    $addressId = null;
    if (!empty($address)) {
        $stmt = $pdo->prepare("
            INSERT INTO addresses
            (user_id, name, phone, address_line1, address_line2, city, state, postal_code, country)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $address['name'] ?? $customerName,
            $address['phone'] ?? $customerPhone,
            $address['address_line1'] ?? '',
            $address['address_line2'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['postal_code'] ?? '',
            $address['country'] ?? 'India'
        ]);
        $addressId = $pdo->lastInsertId();
    }

    /* ----------------------------------------
       Shipping
    ---------------------------------------- */
    $ship = $shippingSame ? $address : ($shipping ?: $address);

    /* ----------------------------------------
       Create order (FIXED)
    ---------------------------------------- */
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            customer_name, customer_email,
            total_amount, delivery_charge, discount,
            payment_method, payment_status, order_status,
            address_id,
            shipping_same_as_billing,
            shipping_name, shipping_phone,
            shipping_address_line1, shipping_address_line2,
            shipping_city, shipping_state, shipping_postal_code, shipping_country
        ) VALUES (
            ?, ?, ?, ?, 0,
            ?, 'PENDING', 'PLACED',
            ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $customerName,
        $customerEmail,
        $totalAmount,
        $deliveryCharge,
        $paymentMethod,
        $addressId,
        $shippingSame,
        $ship['name'] ?? $customerName,
        $ship['phone'] ?? $customerPhone,
        $ship['address_line1'] ?? '',
        $ship['address_line2'] ?? '',
        $ship['city'] ?? '',
        $ship['state'] ?? '',
        $ship['postal_code'] ?? '',
        $ship['country'] ?? 'India'
    ]);

    $orderId = (int)$pdo->lastInsertId();

    /* ----------------------------------------
       Order items
    ---------------------------------------- */
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items as $i) {
        $stmt->execute([$orderId, $i['product_id'], $i['quantity'], $i['price']]);
    }

    /* ----------------------------------------
       COD
    ---------------------------------------- */
    if ($paymentMethod === 'COD') {
        $stk = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        foreach ($items as $i) {
            $stk->execute([$i['quantity'], $i['product_id']]);
        }

        $pdo->commit();
        clearCart();

        echo json_encode([
            'success' => true,
            'order_id' => $orderId,
            'whatsapp_url' => buildWhatsAppAdminUrl($orderId, $totalAmount, $customerName)
        ]);
        exit;
    }

    /* ----------------------------------------
       Razorpay
    ---------------------------------------- */
    $amountPaise = (int)($totalAmount * 100);
    $payload = [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => 'order_' . $orderId
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $rz = json_decode($resp, true);
    if (empty($rz['id'])) throw new Exception('Razorpay order failed');

    $pdo->prepare("UPDATE orders SET razorpay_order_id = ? WHERE id = ?")
        ->execute([$rz['id'], $orderId]);

    $pdo->commit();
    clearCart();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'razorpay' => [
            'order_id' => $rz['id'],
            'amount' => $amountPaise,
            'currency' => 'INR',
            'key' => RAZORPAY_KEY_ID
        ]
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
