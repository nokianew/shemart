<?php
// admin/admin_delete.php
// Delete admin account (superadmin only). Uses CSRF and confirmation via POST.

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

// start session if needed for CSRF/flash
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF token
if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['_csrf'];

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// id
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    $_SESSION['flash'] = ['type'=>'danger','msg'=>'Missing admin id.'];
    header('Location: dashboard.php#tab-admin-management');
    exit;
}

// fetch admin
try {
    $st = $pdo->prepare('SELECT id, username, is_super FROM admin_users WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $row = false;
}
if (!$row) {
    $_SESSION['flash'] = ['type'=>'danger','msg'=>'Admin not found.'];
    header('Location: dashboard.php#tab-admin-management');
    exit;
}

// ensure only superadmin may delete
if (empty($_SESSION['admin']['is_super'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// safety: cannot delete self
$currentAdminId = (int)($_SESSION[ADMIN_SESSION_KEY] ?? ($_SESSION['admin']['id'] ?? 0));
if ($currentAdminId === (int)$id) {
    $_SESSION['flash'] = ['type'=>'danger','msg'=>'You cannot delete your own account.'];
    header('Location: dashboard.php#tab-admin-management');
    exit;
}

// If POST => perform delete. If GET => show confirmation form.
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (empty($_POST['_csrf']) || !hash_equals($_SESSION['_csrf'], (string)$_POST['_csrf'])) {
        $errors[] = 'Invalid CSRF token.';
    }

    if (empty($errors)) {
        try {
            // Option: soft-delete (set active = 0) — here we'll hard-delete
            $st = $pdo->prepare('DELETE FROM admin_users WHERE id = ?');
            $ok = $st->execute([$id]);
            if ($ok) {
                $_SESSION['flash'] = ['type'=>'success','msg'=>'Admin deleted.'];
                header('Location: dashboard.php#tab-admin-management');
                exit;
            } else {
                $errors[] = 'Failed to delete admin.';
            }
        } catch (Throwable $e) {
            $errors[] = 'DB error: ' . $e->getMessage();
        }
    }
}

// header
$page_title = "Delete Admin";
require_once __DIR__ . '/_admin_header.php';
?>

<div style="max-width:720px;margin:18px auto;">
  <h2>Delete Admin — <?= esc($row['username']) ?></h2>

  <?php if (!empty($errors)): ?>
    <div style="padding:10px;background:#fff5f5;border:1px solid #f8d7da;color:#842029;border-radius:6px;margin-bottom:12px;">
      <?php foreach($errors as $e) echo '<div>'.esc($e).'</div>'; ?>
    </div>
  <?php endif; ?>

  <div style="background:#fff;border-radius:8px;padding:18px;box-shadow:0 6px 18px rgba(0,0,0,0.04);">
    <p>Are you sure you want to permanently delete admin <strong><?= esc($row['username']) ?></strong>?</p>

    <form method="post" style="display:inline-block;">
      <input type="hidden" name="_csrf" value="<?= esc($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

      <button type="submit" style="padding:8px 14px;border-radius:6px;border:0;background:#ef4444;color:#fff;margin-right:8px;">Yes, delete</button>
      <a href="dashboard.php#tab-admin-management" style="padding:8px 12px;border-radius:6px;border:1px solid #e5e7eb;text-decoration:none;color:#374151;background:#fff;">Cancel</a>
    </form>
  </div>
</div>
</body>
</html>
