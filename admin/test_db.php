<?php
// Use centralized admin auth (do NOT use session_start()/session_name() here)
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

// Optional: require a role (future use)
// requireRole('admin'); // uncomment when role-based permissions implemented

// admin/test_db.php — DB connection test for SheMart
// Place this in admin/ and open in browser: http://localhost/womenshop/admin/test_db.php

// Try to include same bootstrap you use for admin pages
// Adjust path if your project uses a different file to bootstrap DB connection
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    // If your project uses $pdo, test it. If it uses $db or $conn, try those too.
    $found = [];
    if (isset($pdo) && $pdo instanceof PDO) {
        $found['pdo'] = true;
        $stmt = $pdo->query("SELECT 1 as ok");
        $found['query_result'] = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // try other common names
        if (isset($db)) $found['db'] = true;
        if (isset($conn)) $found['conn'] = true;
        if (empty($found)) throw new Exception('No $pdo/$db/$conn variable found in includes/functions.php');
    }
    echo json_encode(['ok' => true, 'found' => $found]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
