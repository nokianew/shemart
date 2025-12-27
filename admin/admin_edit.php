<?php
// admin/admin_edit.php

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/functions.php';
$pdo = getDB();

if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================
   CURRENT ADMIN CONTEXT
========================= */
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);
$isSuper = !empty($_SESSION['admin']['is_super']) || !empty($_SESSION['is_super']);

if (!$currentAdminId) {
    header('Location: login.php');
    exit;
}

/* =========================
   TARGET ADMIN
========================= */
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    $_SESSION['flash'] = ['type'=>'danger','msg'=>'Missing admin ID'];
    header('Location: dashboard.php#tab-admin-management');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, username, display_name, email, role, is_super, profile_image
    FROM admin_users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    $_SESSION['flash'] = ['type'=>'danger','msg'=>'Admin not found'];
    header('Location: dashboard.php#tab-admin-management');
    exit;
}

/* =========================
   PERMISSION RULE
========================= */
// ✔ Super admin can edit anyone
// ✔ Normal admin can edit only self
if (!$isSuper && $currentAdminId !== (int)$admin['id']) {
    $_SESSION['flash'] = ['type'=>'danger','msg'=>'You do not have permission to edit this admin'];
    header('Location: dashboard.php#tab-admin-management');
    exit;
}

/* =========================
   CSRF
========================= */
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['_csrf'];

function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$errors = [];

/* =========================
   SAVE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['_csrf'], $_POST['_csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    }

    $display_name = trim($_POST['display_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = trim($_POST['password'] ?? '');
    $role         = $isSuper ? ($_POST['role'] ?? $admin['role']) : $admin['role'];
    $makeSuper    = $isSuper && !empty($_POST['is_super']) ? 1 : (int)$admin['is_super'];

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    if ($password && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    if (!$errors) {
        $fields = [
            'display_name = ?',
            'email = ?',
            'role = ?',
            'is_super = ?'
        ];
        $params = [$display_name, $email, $role, $makeSuper];

        if ($password) {
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $params[] = $admin['id'];

        $sql = "UPDATE admin_users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['flash'] = ['type'=>'success','msg'=>'Admin updated successfully'];
        header('Location: dashboard.php#tab-admin-management');
        exit;
    }
}

/* =========================
   UI
========================= */
$page_title = "Edit Admin";
require_once __DIR__ . '/_admin_header.php';
?>

<div class="container" style="max-width:720px;margin:30px auto;">
  <h2>Edit Admin</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e) echo '<div>'.esc($e).'</div>'; ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="_csrf" value="<?= esc($csrf) ?>">

    <div class="mb-3">
      <label class="form-label">Username</label>
      <input class="form-control" value="<?= esc($admin['username']) ?>" disabled>
    </div>

    <div class="mb-3">
      <label class="form-label">Display name</label>
      <input name="display_name" class="form-control"
             value="<?= esc($_POST['display_name'] ?? $admin['display_name']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Email</label>
      <input name="email" type="email" class="form-control"
             value="<?= esc($_POST['email'] ?? $admin['email']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">New password (optional)</label>
      <input name="password" type="password" class="form-control">
    </div>

    <?php if ($isSuper): ?>
      <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <?php
            $roles = ['admin','product_manager','order_manager','support','viewer','inventory_manager','superadmin'];
            foreach ($roles as $r) {
              $sel = (($admin['role'] === $r) ? 'selected' : '');
              echo "<option value='$r' $sel>$r</option>";
            }
          ?>
        </select>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_super" value="1"
          <?= $admin['is_super'] ? 'checked' : '' ?>>
        <label class="form-check-label">Make Super Admin</label>
      </div>
    <?php endif; ?>

    <button class="btn btn-primary">Save</button>
    <a href="dashboard.php#tab-admin-management" class="btn btn-secondary">Cancel</a>
  </form>
</div>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
