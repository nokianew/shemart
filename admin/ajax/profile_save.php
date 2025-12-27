<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();

header('Content-Type: application/json');

try {
    // 1. Ensure logged-in admin
    $adminId = $_SESSION['admin_id'] ?? 0;
    if (!$adminId) {
        throw new Exception('Not authenticated');
    }

    // 2. Read inputs safely
    $displayName = trim($_POST['display_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    // 3. Validate inputs
    if ($displayName === '') {
        throw new Exception('Display name is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    if ($newPassword !== '' && strlen($newPassword) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    // 4. Update DB (with or without password)
    if ($newPassword !== '') {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "UPDATE admin_users 
             SET display_name = ?, email = ?, password_hash = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $displayName,
            $email,
            $passwordHash,
            $adminId
        ]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE admin_users 
             SET display_name = ?, email = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $displayName,
            $email,
            $adminId
        ]);
    }

    // 5. Sync session data
    $_SESSION['admin']['display_name'] = $displayName;
    $_SESSION['admin']['email']        = $email;

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
