<?php
// includes/permissions.php
// RBAC helpers: getAdminPermissions, refreshSessionPermissions, can(), requirePermission()

if (session_status() === PHP_SESSION_NONE) {
    // do not start session if another file already started it, but safe-guard
    @session_start();
}

/**
 * Get a PDO instance - try common helpers first, then fallback to global $pdo, then create one.
 */
function _perm_get_pdo() {
    // prefer a project helper getDB()
    if (function_exists('getDB')) {
        try { return getDB(); } catch (Throwable $e) { /* continue */ }
    }
    // sometimes projects create $pdo in global scope
    global $pdo;
    if (!empty($pdo) && $pdo instanceof PDO) return $pdo;

    // fallback: attempt to create a PDO from config.php variables (if present)
    // NOTE: adjust defaults if your config differs
    try {
        return new PDO(
            "mysql:host=127.0.0.1;dbname=womenshop;charset=utf8mb4",
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (Throwable $e) {
        error_log("permissions.php: cannot create PDO - " . $e->getMessage());
        return null;
    }
}

/**
 * Returns array of permission names for given admin id.
 * Also returns roles assigned to the admin (role names).
 *
 * Structure:
 * [
 *   'roles' => ['super', 'admin'],
 *   'perms' => ['orders.view','orders.update_status', ...]
 * ]
 */
function getAdminPermissions(int $adminId) : array {
    $pdo = _perm_get_pdo();
    if (!$pdo) return ['roles'=>[], 'perms'=>[]];

    try {
        // 1) If admins table has is_super column, give super all permissions (shortcut)
        $st = $pdo->prepare("SELECT is_super, role FROM admin_users WHERE id = :id LIMIT 1");
        $st->execute([':id' => $adminId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['roles'=>[], 'perms'=>[]];

        $roles = [];
        $perms = [];

        if (!empty($row['is_super'])) {
            // superadmin: list role name 'super' if exists, and return all permissions
            $roles[] = 'super';
            $pstmt = $pdo->prepare("SELECT name FROM permissions ORDER BY id");
            $pstmt->execute();
            $all = $pstmt->fetchAll(PDO::FETCH_COLUMN, 0);
            $perms = $all ?: [];
            return ['roles'=>$roles, 'perms'=>$perms];
        }

        // 2) normal admin: locate role name and load permissions via role_permissions
        $roleName = $row['role'] ?? null;
        if ($roleName) {
            // ensure role exists
            $rs = $pdo->prepare("SELECT id, name FROM roles WHERE name = :name LIMIT 1");
            $rs->execute([':name' => $roleName]);
            $rrow = $rs->fetch(PDO::FETCH_ASSOC);
            if ($rrow) {
                $roles[] = $rrow['name'];
                $roleId = (int)$rrow['id'];
                // load permissions for that role
                $ps = $pdo->prepare("
                    SELECT p.name
                    FROM role_permissions rp
                    JOIN permissions p ON p.id = rp.permission_id
                    WHERE rp.role_id = :rid
                    ORDER BY p.name
                ");
                $ps->execute([':rid' => $roleId]);
                $perms = $ps->fetchAll(PDO::FETCH_COLUMN, 0);
            }
        }

        // 3) also load any direct role_permissions mapping by admin_id (if your schema supports admin_roles table)
        // (Many setups use role_permissions only, so this is defensive.)
        // Example: some projects map admins->roles in admin_roles table; adjust if needed.
        // If you have admin_roles table, uncomment and adapt the following block:
        /*
        $ars = $pdo->prepare("
            SELECT r.name
            FROM admin_roles ar
            JOIN roles r ON r.id = ar.role_id
            WHERE ar.admin_id = :aid
        ");
        $ars->execute([':aid' => $adminId]);
        $assigned = $ars->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($assigned as $rn) {
            if (!in_array($rn, $roles, true)) $roles[] = $rn;
        }
        */

        return ['roles'=>$roles, 'perms'=>$perms];
    } catch (Throwable $e) {
        error_log("permissions.php:getAdminPermissions error: " . $e->getMessage());
        return ['roles'=>[], 'perms'=>[]];
    }
}

/**
 * Refresh session cache of roles + perms for the given admin id.
 * Writes: $_SESSION['roles'] (array of role names), $_SESSION['perms'] (array of perm names)
 */
function refreshSessionPermissions(int $adminId) : void {
    if (session_status() === PHP_SESSION_NONE) @session_start();

    $ap = getAdminPermissions($adminId);
    $_SESSION['roles'] = $ap['roles'] ?? [];
    $_SESSION['perms'] = $ap['perms'] ?? [];

    // also ensure session.admin role fields reflect DB
    if (!isset($_SESSION['admin'])) $_SESSION['admin'] = [];
    if (!empty($_SESSION['admin']['id']) && (int)$_SESSION['admin']['id'] === $adminId) {
        // set role if available
        if (!empty($ap['roles'][0])) $_SESSION['admin']['role'] = $ap['roles'][0];
    }
}

/**
 * Check if current session admin has a permission
 */
function can(string $perm) : bool {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    // super shortcut
    if (!empty($_SESSION['admin']['is_super']) || !empty($_SESSION['is_super'])) return true;
    $perms = $_SESSION['perms'] ?? [];
    return in_array($perm, $perms, true);
}

/**
 * Require a permission and exit with 403 if missing
 */
function requirePermission(string $perm) : void {
    if (!can($perm)) {
        http_response_code(403);
        // if this is an AJAX endpoint return JSON, otherwise plain text
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false, 'message'=>'Forbidden: missing permission']);
            exit;
        }
        echo "Forbidden — you don't have permission to access this page.";
        exit;
    }
}
