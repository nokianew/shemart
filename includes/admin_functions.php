<?php
// includes/admin_functions.php
require_once __DIR__ . '/db.php';

function createAdmin(PDO $pdo, $username, $email, $password, $full_name = '', $is_super = 0) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, full_name, is_super) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hash, $full_name, $is_super]);
    return $pdo->lastInsertId();
}

function updateAdmin(PDO $pdo, $adminId, $data) {
    $fields = [];
    $params = [];
    if(isset($data['username'])) { $fields[] = "username = ?"; $params[] = $data['username']; }
    if(isset($data['email']))    { $fields[] = "email = ?";    $params[] = $data['email']; }
    if(isset($data['full_name'])){ $fields[] = "full_name = ?"; $params[] = $data['full_name']; }
    if(isset($data['status']))   { $fields[] = "status = ?";    $params[] = $data['status']; }
    if(isset($data['password']) && $data['password'] !== '') {
        $fields[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    if(empty($fields)) return false;
    $params[] = $adminId;
    $sql = "UPDATE admins SET ".implode(',', $fields)." WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function getAdminById(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, is_super, status, created_at, password FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAdminByUsernameOrEmail(PDO $pdo, $login) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function assignRole(PDO $pdo, $adminId, $roleId, $assignedBy = null) {
    $stmt = $pdo->prepare("SELECT id FROM admin_roles WHERE admin_id = ? AND role_id = ?");
    $stmt->execute([$adminId, $roleId]);
    if($stmt->fetch()) return true;
    $stmt = $pdo->prepare("INSERT INTO admin_roles (admin_id, role_id, assigned_by) VALUES (?, ?, ?)");
    return $stmt->execute([$adminId, $roleId, $assignedBy]);
}

function removeRole(PDO $pdo, $adminId, $roleId) {
    $stmt = $pdo->prepare("DELETE FROM admin_roles WHERE admin_id = ? AND role_id = ?");
    return $stmt->execute([$adminId, $roleId]);
}

function getAdminRoles(PDO $pdo, $adminId) {
    $stmt = $pdo->prepare("SELECT r.* FROM roles r JOIN admin_roles ar ON r.id = ar.role_id WHERE ar.admin_id = ?");
    $stmt->execute([$adminId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function checkPassword($hash, $password) {
    return password_verify($password, $hash);
}

function logAdminAction(PDO $pdo, $adminId, $action, $metadata = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO admin_audit_log (admin_id, action, metadata, ip, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$adminId, $action, $metadata, $ip, $ua]);
}

function adminHasRole(PDO $pdo, $adminId, $roleName) {
    $stmt = $pdo->prepare("SELECT 1 FROM roles r JOIN admin_roles ar ON r.id = ar.role_id WHERE ar.admin_id = ? AND r.name = ? LIMIT 1");
    $stmt->execute([$adminId, $roleName]);
    return (bool)$stmt->fetch();
}

