<?php
// admin_helpers.php (append or replace relevant parts)

// ensure session
if (session_status() === PHP_SESSION_NONE) session_start();

function adminRequireUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
}

function require_superadmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
}

// NEW: return current admin role (string)
function current_admin_role() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['admin']['role'] ?? 'guest';
}

// NEW: require one of allowed roles (superadmin bypasses)
function require_admin_role(array $allowed = []) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    // superadmin always allowed
    if (!empty($_SESSION['admin']['is_super'])) return true;
    $role = $_SESSION['admin']['role'] ?? '';
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        die('Access denied');
    }
    return true;
}
