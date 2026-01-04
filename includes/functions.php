<?php
/**
 * includes/functions.php
 * General helpers + login attempt system + remember-me token tools.
 *
 * Defensive: will throw if required DB include emits any output (helps detect stray HTML/BOM).
 */

 // Prevent this file from accidentally printing anything
 if (ob_get_level() === 0) ob_start();

 try {
    // Load DB in a defensive way: capture any accidental output
    $db_inc = __DIR__ . '/db.php';
    if (!file_exists($db_inc)) {
        throw new RuntimeException("DB include not found: $db_inc");
    }

    // Temporarily buffer output from db.php to detect stray output/BOM/HTML
    $before = ob_get_length();
    require_once $db_inc;
    $after = ob_get_length();
    $buf = '';
    if ($after !== $before) {
        // There was output — capture and throw so caller knows about stray content
        $buf = ob_get_clean();
        throw new RuntimeException("Included db.php emitted unexpected output (possible BOM or stray HTML): " . (strlen($buf) > 200 ? substr($buf,0,200) . '...' : $buf));
    }

    // If nothing emitted, keep buffer active for other includes (we'll not flush here)
    // but ensure we don't leak during normal execution.
 } catch (Throwable $e) {
    // If buffer active but not needed, clear it so nothing leaks
    if (ob_get_level()) ob_end_clean();
    throw $e;
 }

// Config defaults (unless already defined)
if (!defined('ADMIN_SESSION_KEY')) define('ADMIN_SESSION_KEY', 'admin_id');
if (!defined('ADMIN_SESSION_NAME')) define('ADMIN_SESSION_NAME', 'shemart_admin_session');
if (!defined('REMEMBER_COOKIE_NAME')) define('REMEMBER_COOKIE_NAME', 'shemart_admin_rem');
if (!defined('REMEMBER_COOKIE_LIFETIME')) define('REMEMBER_COOKIE_LIFETIME', 60 * 60 * 24 * 30);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_ATTEMPT_WINDOW_MIN')) define('LOGIN_ATTEMPT_WINDOW_MIN', 15);

// detect HTTPS for cookies
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// -------------------- generic helpers --------------------
function getClientIp(): string {
    if (!empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
    return '0.0.0.0';
}

function slugify_simple(string $s): string {
    $s = preg_replace('/[^\p{L}\p{N}\-]+/u', '-', mb_strtolower(trim($s)));
    $s = preg_replace('/-+/', '-', $s);
    return trim($s, '-');
}

// -------------------- login attempts --------------------
function recordLoginAttempt(string $ip, string $username, bool $success): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO admin_login_attempts (ip_address, username_attempted, success) VALUES (INET6_ATON(?), ?, ?)");
    $stmt->execute([$ip, $username, $success ? 1 : 0]);
}

function countRecentFailures(string $ip, ?string $username = null, int $minutes = LOGIN_ATTEMPT_WINDOW_MIN): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM admin_login_attempts WHERE ip_address = INET6_ATON(?) AND success = 0 AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
    $stmt->execute([$ip, $minutes]);
    $cntIp = (int)$stmt->fetchColumn();

    $cntUser = 0;
    if ($username !== null) {
        $stmt2 = $db->prepare("SELECT COUNT(*) FROM admin_login_attempts WHERE username_attempted = ? AND success = 0 AND attempt_time > (NOW() - INTERVAL ? MINUTE)");
        $stmt2->execute([$username, $minutes]);
        $cntUser = (int)$stmt2->fetchColumn();
    }

    return ['ip' => $cntIp, 'user' => $cntUser];
}

function isBlocked(string $ip, ?string $username = null): bool {
    $counts = countRecentFailures($ip, $username);
    return ($counts['ip'] >= MAX_LOGIN_ATTEMPTS) || ($counts['user'] >= MAX_LOGIN_ATTEMPTS);
}

// -------------------- remember-me tokens --------------------
function createRememberToken(int $admin_id): void {
    $db = getDB();
    $selector = bin2hex(random_bytes(12));
    $validator = random_bytes(32);
    $validator_b64 = base64_encode($validator);
    $token_hash = hash('sha256', $validator);
    $expires_at = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

    $stmt = $db->prepare("INSERT INTO admin_remember_tokens (admin_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$admin_id, $selector, $token_hash, $expires_at]);

    $cookie_value = $selector . ':' . $validator_b64;
    setcookie(REMEMBER_COOKIE_NAME, $cookie_value, [
        'expires'  => time() + REMEMBER_COOKIE_LIFETIME,
        'path'     => '/',
        'secure'   => $GLOBALS['secureCookie'] ?? $secureCookie ?? false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberTokens(?int $admin_id = null): void {
    $db = getDB();
    if ($admin_id) {
        $stmt = $db->prepare("DELETE FROM admin_remember_tokens WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
    } else if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME]);
        if (count($parts) === 2) {
            $selector = $parts[0];
            $stmt = $db->prepare("DELETE FROM admin_remember_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        }
    }
    setcookie(REMEMBER_COOKIE_NAME, '', time() - 3600, '/', '', $GLOBALS['secureCookie'] ?? $secureCookie ?? false, true);
}

function clearRememberTokenBySelector(string $selector): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM admin_remember_tokens WHERE selector = ?");
    $stmt->execute([$selector]);
    setcookie(REMEMBER_COOKIE_NAME, '', time() - 3600, '/', '', $GLOBALS['secureCookie'] ?? $secureCookie ?? false, true);
}

// -------------------- admin login (uses session set by admin_auth.php) --------------------
function adminLogin(string $username, string $password, bool $remember = false): array {
    $db = getDB();
    $ip = getClientIp();

    if (isBlocked($ip, $username)) {
        return ['success' => false, 'message' => 'Too many failed login attempts. Try again later.'];
    }

    $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // set session id (session started by admin_auth.php)
        $_SESSION[ADMIN_SESSION_KEY] = (int)$user['id'];
        @session_regenerate_id(true);

        recordLoginAttempt($ip, $username, true);

        if ($remember) {
            clearRememberTokens((int)$user['id']);
            createRememberToken((int)$user['id']);
        }

        return ['success' => true, 'admin' => $user];
    }

    recordLoginAttempt($ip, $username, false);
    return ['success' => false, 'message' => 'Invalid username or password.'];
}

// We intentionally omit a closing PHP tag to avoid trailing whitespace issues.
// ==============================
// USER AUTH HELPERS (PHASE 1)
// ==============================

/**
 * Is a user logged in?
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user']);
}

/**
 * Get current logged-in user
 */
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Require user to be logged in
 */
function requireUser(): void {
    if (empty($_SESSION['user'])) {
        header('Location: auth.php?tab=login');
        exit;
    }
}
