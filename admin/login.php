<?php
// File: /womenshop/admin/login.php
// Merged UI (polished) + original auth logic
// IMPORTANT: keep session_name so other admin pages remain compatible
session_name('shemart_admin_session');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php'; // RBAC (optional)

// Simple CSRF protection for the login form
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    } catch (Exception $e) {
        // fallback
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(24));
    }
}

$error = '';
$next = $_GET['next'] ?? ($_POST['next'] ?? 'dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $error = 'Invalid request (CSRF token mismatch).';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // DB lookup & verify (this preserves your original logic)
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // DB error - don't leak details
            $admin = false;
        }

        if ($admin && isset($admin['password_hash']) && password_verify($password, $admin['password_hash'])) {

            // Regenerate session id for security
            session_regenerate_id(true);

            // SUCCESS → store full admin details in session (both formats for compatibility)
            $_SESSION['admin'] = [
                'id'           => (int)$admin['id'],
                'username'     => $admin['username'],
                'display_name' => $admin['display_name'] ?? $admin['username'],
                'email'        => $admin['email'] ?? '',
                'profile_image'=> $admin['profile_image'] ?? '',
                'is_super'     => (int)($admin['is_super'] ?? 0),
                'role'         => $admin['role'] ?? 'admin'
            ];

            // Also set admin_id (some parts of the app expect this)
            $_SESSION['admin_id'] = (int)$admin['id'];

            // Load roles + permissions into session cache if available
            if (function_exists('refreshSessionPermissions')) {
                refreshSessionPermissions($_SESSION['admin_id']);
            }

            // Regenerate CSRF token after login to avoid reuse
            unset($_SESSION['csrf_token']);

            // Redirect to next or admin dashboard
            // Only allow internal redirects (prevent open redirect)
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
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Login — Shemart</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--bg:#f6f8fb;--card:#fff;--accent:#0ea5e9;--muted:#6b7280}
    body{background:var(--bg);font-family:Inter,system-ui,Arial;color:#111}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{border-radius:10px;box-shadow:0 6px 20px rgba(16,24,40,0.06)}
    .muted{color:var(--muted)}
    .btn-primary{background:#0ea5e9;border-color:#0ea5e9}
    .btn-primary:hover{background:#0aa3d6;border-color:#0aa3d6}
    .small-note{font-size:13px;color:var(--muted)}
    @media (max-width:480px){
      .container{padding:16px}
    }

    /* animated avatar */
    .avatar-circle {
      width:46px;
      height:46px;
      border-radius:50%;
      background:#e6eef6;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:700;
      color:#054a65;
      font-size:14px;
      transition: transform 220ms cubic-bezier(.2,.9,.2,1), opacity 220ms ease;
      transform-origin: center center;
      opacity: 1;
    }

    /* hidden state (fully removed) */
    .d-none {
      display: none !important;
      opacity: 0;
    }

    /* helper visible state: element in flow but prepared for animation */
    .avatar-visible {
      display: inline-flex !important;
      opacity: 0;
      transform: scale(0.88);
    }

    /* animate to full */
    .avatar-fade-in {
      opacity: 1;
      transform: scale(1);
    }
  </style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">
  <div class="card p-4 shadow-sm" style="max-width:520px; width:100%;">
    <div class="d-flex align-items-center gap-3 mb-3">
      <!-- avatar is hidden initially; JS will show and set initials when typing -->
      <div id="avatarCircle" class="avatar-circle d-none" aria-hidden="true"></div>
      <div>
        <h4 class="mb-0">Admin Login</h4>
        <div class="muted small-note">Sign in to your Shemart admin dashboard</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="mt-2" autocomplete="off">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Username</label>
          <input id="usernameField" type="text" name="username" class="form-control" required autofocus value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-12 d-flex align-items-center justify-content-between">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="" id="rememberMe">
            <label class="form-check-label small muted" for="rememberMe">Remember me</label>
          </div>
          <button class="btn btn-primary">Login</button>
        </div>
      </div>
    </form>

    <div class="mt-3 small-note">If you face issues logging in, use the original admin login or contact the site administrator.</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const usernameInput = document.querySelector('input[name="username"]');
    const avatarCircle = document.getElementById('avatarCircle');

    function setInitialsFrom(value) {
        const parts = value.split(/[^a-zA-Z0-9]+/).filter(Boolean);
        let initials = "";
        if (parts.length === 0) return "";
        if (parts.length === 1) {
            initials = (parts[0].substring(0, 2)).toUpperCase();
        } else {
            initials = (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return initials;
    }

    function updateAvatar() {
        const value = usernameInput.value.trim();
        if (!value) {
            // hide smoothly
            if (avatarCircle.classList.contains('avatar-fade-in')) {
                avatarCircle.classList.remove('avatar-fade-in');
                setTimeout(() => {
                    avatarCircle.classList.remove('avatar-visible');
                    avatarCircle.classList.add('d-none');
                }, 240); // slightly more than CSS transition
            } else {
                avatarCircle.classList.remove('avatar-visible');
                avatarCircle.classList.add('d-none');
            }
            return;
        }

        const initials = setInitialsFrom(value);
        avatarCircle.textContent = initials;

        // if currently hidden, enable visible helper then trigger fade-in
        if (avatarCircle.classList.contains('d-none')) {
            avatarCircle.classList.remove('d-none');
            avatarCircle.classList.add('avatar-visible');
            // trigger animation on next frame
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    avatarCircle.classList.add('avatar-fade-in');
                });
            });
            return;
        }

        // if visible, ensure fade-in state is present and update initials
        avatarCircle.classList.add('avatar-fade-in');
    }

    // If field exists and has a prefilled value (old POST), initialize avatar
    if (usernameInput && usernameInput.value && usernameInput.value.trim()) {
        const initials = setInitialsFrom(usernameInput.value.trim());
        avatarCircle.textContent = initials;
        avatarCircle.classList.remove('d-none');
        avatarCircle.classList.add('avatar-visible', 'avatar-fade-in');
    }

    if (usernameInput) usernameInput.addEventListener('input', updateAvatar);
});
</script>
</body>
</html>
