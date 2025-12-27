<?php
// admin/logout.php
// Safe logout that destroys session, clears remember-me token, and redirects to login.php

// show errors during dev only (optional)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Try to include helpers (optional, not fatal)
$included = false;
if (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
    $included = true;
}
if (file_exists(__DIR__ . '/../includes/admin_auth.php')) {
    // admin_auth may define constants like ADMIN_SESSION_NAME and helper functions
    require_once __DIR__ . '/../includes/admin_auth.php';
    $included = true;
}

// Use configured session name if present
$sessionName = defined('ADMIN_SESSION_NAME') ? ADMIN_SESSION_NAME : (defined('PHPSESSID') ? PHPSESSID : session_name());

// Start session safely if not started
if (session_status() === PHP_SESSION_NONE) {
    // Use the same name as other admin pages
    if (!headers_sent()) {
        session_name($sessionName);
    } else {
        // headers already sent — still try to continue
        @session_name($sessionName);
    }
    @session_start();
}

// Optionally clear remember token by selector or admin id
try {
    // prefer using helper if available
    if (function_exists('clearRememberTokens')) {
        // clear tokens for current admin id if available, otherwise clear from cookie
        $adminId = $_SESSION['admin']['id'] ?? $_SESSION[ADMIN_SESSION_KEY] ?? null;
        if ($adminId) {
            clearRememberTokens((int)$adminId);
        } else {
            clearRememberTokens(null); // will attempt to clear based on cookie
        }
    } else {
        // fallback: explicitly clear cookie if set
        if (defined('REMEMBER_COOKIE_NAME') && !empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
            setcookie(REMEMBER_COOKIE_NAME, '', time() - 3600, '/', '', false, true);
            unset($_COOKIE[REMEMBER_COOKIE_NAME]);
        } elseif (!empty($_COOKIE['shemart_admin_rem'])) {
            setcookie('shemart_admin_rem', '', time() - 3600, '/', '', false, true);
            unset($_COOKIE['shemart_admin_rem']);
        }
    }
} catch (Throwable $e) {
    // ignore—don't break logout on DB errors
}

// Clear session array
$_SESSION = [];

// Delete session cookie if exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"] ?? '/',
        $params["domain"] ?? '',
        $params["secure"] ?? false,
        $params["httponly"] ?? true
    );
}

// Finally destroy the session
@session_destroy();

// Redirect to login page. Use absolute or relative path depending on your app structure
$loginUrl = 'login.php'; // adjust if your login file is at different path

// If headers not already sent, send Location header
if (!headers_sent()) {
    header('Location: ' . $loginUrl);
    exit;
}

// Fallback HTML/JS redirect if headers already sent
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Logging out…</title>
  <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">
  <style>body{font-family:system-ui,Arial;padding:30px;color:#222}</style>
</head>
<body>
  <p>If you are not redirected automatically, <a id="lnk" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">click here to continue</a>.</p>
  <script>
    try {
      window.location.replace(<?php echo json_encode($loginUrl); ?>);
    } catch(e) {
      // last fallback
      document.getElementById('lnk').click();
    }
  </script>
</body>
</html>
