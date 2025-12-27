<?php
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

header('Content-Type: application/json');

/* ===============================
   BASIC AUTH CHECK
================================ */
$adminId = $_SESSION['admin']['id'] ?? null;
if (!$adminId) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

/* ===============================
   ONLY ALLOW POST
================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

/* ===============================
   CSRF CHECK
================================ */
$csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['ok' => false, 'error' => 'CSRF validation failed']);
    exit;
}

/* ===============================
   ACTION ROUTER
================================ */
$action = $_POST['action'] ?? '';

/* ======================================================
   UPDATE DISPLAY NAME
====================================================== */
if ($action === 'update_profile') {

    $name = trim($_POST['display_name'] ?? '');
    if ($name === '') {
        echo json_encode(['ok' => false, 'error' => 'Name is required']);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE admin_users SET display_name = ? WHERE id = ?"
    );
    $stmt->execute([$name, $adminId]);

    $_SESSION['admin']['display_name'] = $name;

    echo json_encode(['ok' => true, 'message' => 'Name updated']);
    exit;
}

/* ======================================================
   UPDATE EMAIL + PASSWORD
====================================================== */
if ($action === 'update_settings') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid email address']);
        exit;
    }

    // Update email
    if ($password !== '') {

        if (strlen($password) < 8) {
            echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "UPDATE admin_users SET email = ?, password_hash = ? WHERE id = ?"
        );
        $stmt->execute([$email, $hash, $adminId]);

    } else {

        $stmt = $pdo->prepare(
            "UPDATE admin_users SET email = ? WHERE id = ?"
        );
        $stmt->execute([$email, $adminId]);
    }

    $_SESSION['admin']['email'] = $email;

    echo json_encode(['ok' => true, 'message' => 'Account updated']);
    exit;
}

/* ===============================
   FALLBACK
================================ */
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
exit;
