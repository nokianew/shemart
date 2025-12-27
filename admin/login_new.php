<?php
// File: login_new.php (updated to match your real login/session behavior)
session_name('shemart_admin_session');
if (session_status() === PHP_SESSION_NONE) session_start();

// include your app's config and helpers so DB / auth functions are available
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php'; // optional, safe if present

// SAFE helper: if you have an admin_authenticate wrapper you can keep it,
// otherwise we'll use a local DB check via PDO + password_verify below.
if (!function_exists('check_admin_credentials')) {
    function check_admin_credentials($username, $password) {
        // prefer existing app function if present
        if (function_exists('admin_authenticate')) {
            return admin_authenticate($username, $password);
        }

        // otherwise attempt DB lookup using $pdo from config.php (expected)
        global $pdo;
        if (empty($pdo)) return false;

        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) return false;
        if (!isset($admin['password_hash'])) return false;

        if (password_verify($password, $admin['password_hash'])) {
            // store full admin row in session in the same shape your app expects
            $_SESSION['admin'] = [
                'id'           => (int)$admin['id'],
                'username'     => $admin['username'],
                'display_name' => $admin['display_name'] ?? $admin['username'],
                'email'        => $admin['email'] ?? '',
                'profile_image'=> $admin['profile_image'] ?? '',
                'is_super'     => (int)($admin['is_super'] ?? 0),
                'role'         => $admin['role'] ?? 'admin'
            ];
            $_SESSION['admin_id'] = (int)$admin['id'];

            // load permissions cache if function exists
            if (function_exists('refreshSessionPermissions')) {
                refreshSessionPermissions($_SESSION['admin_id']);
            }

            return true;
        }

        return false;
    }
}

$error = '';
$next = $_GET['next'] ?? ($_POST['next'] ?? 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please provide username and password.';
    } else {
        if (check_admin_credentials($username, $password)) {
            // regenerate for session fixation protection
            session_regenerate_id(true);

            // Redirect to allowed next or dashboard
            if ($next && strpos($next, '/') === 0) {
                header("Location: {$next}");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login — Shemart (Test)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* minimal cosmetic tweaks to match login_new look */
    body.bg-light { background: #f6f8fb; }
    .card { border-radius: 10px; }
  </style>
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">
  <div class="card p-4 shadow-sm" style="max-width:480px; width:100%;">
    <div class="d-flex align-items-center gap-3 mb-3">
      <div style="width:46px;height:46px;border-radius:50%;background:#e6eef6;display:flex;align-items:center;justify-content:center;font-weight:700;color:#054a65">SK</div>
      <div>
        <h4 class="mb-0">Admin Login</h4>
        <div class="text-muted small">Sign in to your Shemart admin dashboard</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="mt-2">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required autofocus value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="d-flex align-items-center justify-content-between">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="" id="rememberMe">
          <label class="form-check-label small text-muted" for="rememberMe">Remember me</label>
        </div>
        <button class="btn btn-primary">Login</button>
      </div>
    </form>

    <div class="mt-3 small text-muted">Tip: demo account <strong>admin@demo</strong> / <strong>password</strong> (remove in production).</div>
    <div class="mt-2">
      <a href="profile_new.php" class="btn btn-outline-primary btn-sm mt-2">Open Profile (test)</a>
      <a href="login.php" class="btn btn-link btn-sm mt-2">Open existing login</a>
    </div>
  </div>
</div>
</body>
</html>
