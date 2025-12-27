<?php
// admin/ajax/test_orders_api.php
// Hardened JSON-only endpoint (safe against accidental include output)

ini_set('display_errors', 0); // don't let PHP print HTML errors into JSON
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// start buffering to prevent any accidental output (HTML/BOM) leaking to client
ob_start();

try {
    // adjust path if your includes are in a different location
    $inc = __DIR__ . '/../../includes/functions.php';
    if (!file_exists($inc)) {
        throw new RuntimeException("Includes file not found: $inc");
    }

    require_once $inc;

    if (!function_exists('getDB')) {
        throw new RuntimeException("getDB() not found in includes/functions.php");
    }

    $pdo = getDB();
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException("getDB() did not return a PDO instance");
    }

    // safe count (use prepared statement if you add conditions later)
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM orders");
    $cnt = (int) $stmt->fetchColumn();

    // discard buffered output (any accidental HTML/BOM) and send JSON
    ob_end_clean();

    echo json_encode(['ok' => true, 'orders_table_count' => $cnt], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
} catch (Throwable $e) {
    // drop any buffered output before returning JSON error
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    $payload = [
        'ok' => false,
        'error' => $e->getMessage(),
        // optionally include file/line when debugging; remove in production
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
