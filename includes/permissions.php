<?php
// includes/permissions.php
// RBAC helpers: getAdminPermissions, refreshSessionPermissions, can(), requirePermission()

/**
 * IMPORTANT RULE:
 * - Permissions MUST use the same PDO as the rest of the app
 * - No localhost / root / new PDO here
 * - Fail closed (secure) if DB is unavailable
 */

/**
 * Get PDO for permissions (single source of truth)
 */
function _perm_get_pdo(): PDO
{
    if (function_exists('getDB')) {
        return getDB();
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    throw new RuntimeException('Database connection not available for permissions');
}

/**
 * Returns roles & permissions for an admin
 */
function getAdminPermissions(int $adminId): array
{
    $pdo = _perm_get_pdo();

    // 1️⃣ Fetch admin base info
    $st = $pdo->prepare("SELECT is_super, role FROM admin_users WHERE id = :id LIMIT 1");
    $st->execute([':id' => $adminId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['roles' => [], 'perms' => []];
    }

    $roles = [];
    $perms = [];

    // 2️⃣ Super admin shortcut
    if (!empty($row['is_super'])) {
        $roles[] = 'super';

        $pstmt = $pdo->query("SELECT name FROM permissions ORDER BY id");
        $perms = $pstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return ['roles' => $roles, 'perms' => $perms];
    }

    // 3️⃣ Role-based permissions
    if (!empty($row['role'])) {
        $rs = $pdo->prepare("SELECT id, name FROM roles WHERE name = :name LIMIT 1");
        $rs->execute([':name' => $row['role']]);
        $rrow = $rs->fetch(PDO::FETCH_ASSOC);

        if ($rrow) {
            $roles[] = $rrow['name'];

            $ps = $pdo->prepare("
                SELECT p.name
                FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role_id = :rid
                ORDER BY p.name
            ");
            $ps->execute([':rid' => (int)$rrow['id']]);
            $perms = $ps->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
    }

    return ['roles' => $roles, 'perms' => $perms];
}

/**
 * Refresh permissions stored in session
 */
function refreshSessionPermissions(int $adminId): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $ap = getAdminPermissions($adminId);

    $_SESSION['roles'] = $ap['roles'] ?? [];
    $_SESSION['perms'] = $ap['perms'] ?? [];

    if (!isset($_SESSION['admin'])) {
        $_SESSION['admin'] = [];
    }

    if (!empty($_SESSION['admin']['id']) && (int)$_SESSION['admin']['id'] === $adminId) {
        if (!empty($ap['roles'][0])) {
            $_SESSION['admin']['role'] = $ap['roles'][0];
        }
    }
}

/**
 * Check permission
 */
function can(string $perm): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // super admin shortcut
    if (!empty($_SESSION['admin']['is_super']) || !empty($_SESSION['is_super'])) {
        return true;
    }

    return in_array($perm, $_SESSION['perms'] ?? [], true);
}

/**
 * Enforce permission
 */
function requirePermission(string $perm): void
{
    if (!can($perm)) {
        http_response_code(403);

        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Forbidden: missing permission']);
            exit;
        }

        echo "Forbidden — you don't have permission to access this page.";
        exit;
    }
}
