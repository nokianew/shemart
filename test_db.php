<?php
// test_db.php - simple DB diagnostic for getDB()
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/includes/db.php';
    $pdo = getDB();
    if ($pdo instanceof PDO) {
        echo json_encode(['ok' => true, 'msg' => 'getDB() OK', 'server' => php_uname('s')]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'getDB() did not return PDO']);
    }
} catch (Throwable $e) {
    // Return clean JSON error (no HTML)
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
