<?php
// Use centralized admin auth (do NOT use session_start()/session_name() here)
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

// Optional: require a role (future use)
// requireRole('admin'); // uncomment when role-based permissions implemented

require_once __DIR__ . '/../includes/db.php'; // sets $pdo
require_once __DIR__ . '/../includes/roles.php';


$changerAdminId = intval($_SESSION['admin_id']);
$targetAdminId = isset($_POST['target_admin_id']) ? intval($_POST['target_admin_id']) : 0;
$selectedRoles = isset($_POST['roles']) ? array_map('intval', $_POST['roles']) : [];

// optional permission: only super_admin can change roles
if (!adminHasRole($pdo, $changerAdminId, 'super_admin')) {
    header("Location: admins_list.php?error=permission");
    exit;
}

try {
    assignRolesToAdmin($pdo, $changerAdminId, $targetAdminId, $selectedRoles);
    header("Location: admins_list.php?success=roles_updated");
} catch (Exception $e) {
    error_log("Role update failed: ".$e->getMessage());
    header("Location: admins_list.php?error=role_failed");
}
exit;
