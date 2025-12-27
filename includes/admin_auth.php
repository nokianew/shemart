<?php
// includes/admin_auth.php
// Centralized admin authentication + csrf + RBAC bootstrap

// Load basic DB helper if present (getDB)
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

// SESSION constants
if (!defined('ADMIN_SESSION_NAME')) define('ADMIN_SESSION_NAME', 'shemart_admin_session');
if (!defined('ADMIN_SESSION_KEY'))  define('ADMIN_SESSION_KEY', 'admin_id');
if (!defined('REMEMBER_COOKIE_NAME')) define('REMEMBER_COOKIE_NAME', 'shemart_admin_rem');

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    @session_name(ADMIN_SESSION_NAME);
    @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

/* -------------------------------------------------
   🔥 IMPORTANT — LOAD RBAC PERMISSIONS SYSTEM HERE
---------------------------------------------------*/
if (file_exists(__DIR__ . '/permissions.php')) {
    require_once __DIR__ . '/permissions.php';
}
// This ensures ALL admin pages automatically load RBAC,
// including can(), requirePermission(), refreshSessionPermissions().

/* --------------------------
   CSRF HELPERS
--------------------------*/
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return (string) ($_SESSION['csrf_token'] ?? '');
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($provided): bool {
        if (!is_string($provided) || $provided === '') return false;
        $stored = $_SESSION['csrf_token'] ?? '';
        return hash_equals($stored, $provided);
    }
}

/* --------------------------
   ADMIN IDENTITY HELPERS
--------------------------*/
if (!function_exists('currentAdmin')) {
    function currentAdmin(): ?array {
        if (!empty($_SESSION['admin'])) return $_SESSION['admin'];

        if (!empty($_SESSION[ADMIN_SESSION_KEY])) {
            $id = (int)$_SESSION[ADMIN_SESSION_KEY];

            if (function_exists('getDB')) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        SELECT id, username, role, COALESCE(is_super,0) AS is_super, email, display_name
                        FROM admin_users WHERE id = ? LIMIT 1
                    ");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($row) {
                        $row['is_super'] = (int)$row['is_super'];
                        $_SESSION['admin'] = $row;
                        return $row;
                    }
                } catch (Exception $e) {}
            }
            unset($_SESSION[ADMIN_SESSION_KEY], $_SESSION['admin']);
        }
        return null;
    }
}

if (!function_exists('currentAdminId')) {
    function currentAdminId(): ?int {
        return $_SESSION['admin']['id'] ?? ($_SESSION[ADMIN_SESSION_KEY] ?? null);
    }
}

if (!function_exists('adminHasRole')) {
    function adminHasRole($roleOrArray): bool {
        $a = currentAdmin();
        if (!$a) return false;
        if (!empty($a['is_super'])) return true;

        $roles = is_array($roleOrArray) ? $roleOrArray : [$roleOrArray];
        return !empty($a['role']) && in_array($a['role'], $roles);
    }
}

/* --------------------------
   REQUIRE LOGIN
--------------------------*/
if (!function_exists('adminRequireUser')) {
    function adminRequireUser($next = null) {
        if (currentAdmin()) return true;

        $next = $next ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $login = dirname($_SERVER['SCRIPT_NAME']) . '/login.php?next=' . urlencode($next);

        header('Location: ' . $login);
        exit;
    }
}

/* --------------------------
   CREATE ADMIN SESSION
--------------------------*/
if (!function_exists('createAdminSession')) {
    function createAdminSession(int $admin_id): bool {
        $_SESSION[ADMIN_SESSION_KEY] = $admin_id;

        if (function_exists('getDB')) {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    SELECT id, username, role, COALESCE(is_super,0) AS is_super, email, display_name
                    FROM admin_users WHERE id = ? LIMIT 1
                ");
                $stmt->execute([$admin_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $row['is_super'] = (int)$row['is_super'];
                    $_SESSION['admin'] = $row;
                }
            } catch (Exception $e) {}
        }

        // Refill RBAC perms for new admin session (if permissions helper available)
        if (function_exists('refreshSessionPermissions')) {
            @refreshSessionPermissions($admin_id);
        }

        @session_regenerate_id(true);
        return true;
    }
}

/* --------------------------
   LOGOUT
--------------------------*/
if (!function_exists('adminLogout')) {
    function adminLogout() {
        if (!empty($_SESSION[ADMIN_SESSION_KEY]) && function_exists('clearRememberTokens')) {
            clearRememberTokens((int)$_SESSION[ADMIN_SESSION_KEY]);
        }

        unset($_SESSION['admin'], $_SESSION[ADMIN_SESSION_KEY]);

        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $p["path"], $p["domain"] ?? '', $p["secure"] ?? false, $p["httponly"] ?? false
            );
        }

        @session_destroy();
    }
}
