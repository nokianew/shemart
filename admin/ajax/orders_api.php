<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();

/* ---------------- HELPERS ---------------- */
function ok($data = []) {
    echo json_encode(['success' => true] + $data);
    exit;
}
function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$action = $_GET['action'] ?? '';

/* =========================
   LIST ORDERS
========================= */
if ($action === 'list') {

    $page     = max(1, (int)($_GET['page'] ?? 1));
    $perPage  = max(1, min(50, (int)($_GET['per_page'] ?? 10)));
    $q        = trim($_GET['q'] ?? '');
    $status   = trim($_GET['status'] ?? '');
    $offset   = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    if ($status !== '') {
        $where[] = 'o.order_status = :status';
        $params[':status'] = $status;
    }

    if ($q !== '') {
        if (ctype_digit($q)) {
            $where[] = 'o.id = :oid';
            $params[':oid'] = (int)$q;
        } else {
            $where[] = '(o.customer_name LIKE :q OR o.customer_email LIKE :q OR p.name LIKE :q)';
            $params[':q'] = "%$q%";
        }
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count
    $countSql = "
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN products p ON p.id = oi.product_id
        $whereSQL
    ";
    $st = $pdo->prepare($countSql);
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    // List
    $sql = "
        SELECT
            o.id,
            o.created_at,
            o.total_amount,
            o.order_status AS status,
            o.customer_name,
            o.customer_email,
            o.payment_method,
            o.shipping_name,
            o.shipping_city,
            o.shipping_phone,
            GROUP_CONCAT(CONCAT(p.name,' x',oi.quantity) SEPARATOR ', ') AS items_preview
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN products p ON p.id = oi.product_id
        $whereSQL
        GROUP BY o.id
        ORDER BY o.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();

    ok([
        'data' => $st->fetchAll(PDO::FETCH_ASSOC),
        'page' => $page,
        'per_page' => $perPage,
        'total_count' => $total
    ]);
}

/* =========================
   VIEW ORDER (MODAL)
========================= */
if ($action === 'view') {

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Invalid order id');

    $st = $pdo->prepare("
        SELECT
            o.id,
            o.customer_name,
            o.customer_email,
            o.payment_method,
            o.order_status AS status,

            o.shipping_name,
            o.shipping_address_line1,
            o.shipping_address_line2,
            o.shipping_city,
            o.shipping_state,
            o.shipping_country,
            o.shipping_phone,

            o.total_amount
        FROM orders o
        WHERE o.id = ?
        LIMIT 1
    ");
    $st->execute([$id]);
    $order = $st->fetch(PDO::FETCH_ASSOC);

    if (!$order) fail('Order not found');

    /* ===== BUILD FULL ADDRESS (NO PINCODE COLUMN) ===== */
    $shippingAddressRaw = trim(implode("\n", array_filter([
        $order['shipping_name'],
        $order['shipping_address_line1'],
        $order['shipping_address_line2'],
        $order['shipping_city'],
        $order['shipping_state'],
        $order['shipping_country'],
        'Phone: ' . $order['shipping_phone']
    ])));

    /* ===== ITEMS ===== */
    $it = $pdo->prepare("
        SELECT p.name, oi.quantity, oi.price
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $it->execute([$id]);

    $itemsHtml = '';
    foreach ($it->fetchAll(PDO::FETCH_ASSOC) as $i) {
        $itemsHtml .= htmlspecialchars($i['name']) .
            " x{$i['quantity']} — ₹" .
            number_format($i['price'] * $i['quantity'], 2) .
            "<br>";
    }

    ok([
        'order' => [
            'id' => $order['id'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['shipping_phone'],
            'payment_method' => $order['payment_method'],
            'status' => $order['status'],
            'shipping_address'     => nl2br(htmlspecialchars($shippingAddressRaw)),
            'shipping_address_raw' => $shippingAddressRaw,
            'items_html' => $itemsHtml,
            'total_amount' => $order['total_amount']
        ],
        'invoice_url' => "invoice.php?id={$order['id']}",
        'print_url'   => "invoice.php?id={$order['id']}&print=1"
    ]);
}

/* =========================
   UPDATE STATUS
========================= */
if ($action === 'update_status') {

    $id     = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$id || !$status) fail('Missing data');

    $st = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
    $st->execute([$status, $id]);

    ok(['message' => 'Status updated']);
}

fail('Invalid action');
