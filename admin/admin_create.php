<?php
// Use centralized admin auth (do NOT use session_start()/session_name() here)
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/permissions.php'; // ⬅️ ADDED: RBAC helpers
requirePermission('admins.create'); // ⬅️ ADDED: enforce create-admin permission

// Optional: require a role (future use)
// requireRole('admin'); // uncomment when role-based permissions implemented

// admin_create.php

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config.php'; // provides $pdo

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {

    exit;
}

// ensure logged admin exists
try {
    $st = $pdo->prepare('SELECT id, is_super FROM admin_users WHERE id = ? LIMIT 1');
    $st->execute([$adminId]);
    $me = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $me = null; }

if (!$me) {

    exit;
}

// ONLY super admin can create new admins
if ((int)$me['is_super'] !== 1) {
    echo "Only super admin may create new admins.";
    exit;
}

$errors = [];
$success = false;


// define allowed roles
$validRoles = ['admin','orders_manager','inventory_manager','support','viewer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'admin');
    $is_super = isset($_POST['is_super']) ? 1 : 0;

    if (!in_array($role, $validRoles, true)) {
        $errors[] = "Invalid role selected.";
    }

    if ($username === '' || $password === '') $errors[] = 'Username and password required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

    // duplicate username/email checks
    if (empty($errors)) {
        $st = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        if ($st->fetch()) $errors[] = 'Username exists.';

        if ($email !== '') {
            $st = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            if ($st->fetch()) $errors[] = 'Email already used.';
        }
    }

    // Image upload handling
    $profileImage = 'default.png';
    if (empty($errors) && !empty($_FILES['profile_image']['name'])) {
        $uploadDir = __DIR__ . '/../assets/admin_profile/';
        if (!is_dir($uploadDir)) mkdir($uploadDir,0755,true);

        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];

        if (!in_array($ext, $allowed, true)) $errors[] = 'Invalid image type.';
        else {
            $newName = uniqid('adm_') . '.' . $ext;
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir.$newName)) {
                $errors[] = 'Failed to move uploaded file.';
            } else $profileImage = $newName;
        }
    }

    // Insert new admin
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $st = $pdo->prepare('INSERT INTO admin_users (username, display_name, email, password_hash, profile_image, is_super, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $ok = $st->execute([$username, $display_name, $email, $hash, $profileImage, $is_super, $role]);

        if ($ok) {
            $success = true;
        } else {
            $errors[] = 'DB error creating admin.';
        }
    }
}

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html><head>
  <meta charset="utf-8">
  <title>Create Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2>Create Admin</h2>

  <?php if ($success): ?>
    <div class="alert alert-success">Admin created successfully. 
      <a href="dashboard.php#tab-admin-management">Back to list</a>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach($errors as $e) echo '<div>'.esc($e).'</div>'; ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">

    <div class="mb-3">
      <label>Username</label>
      <input class="form-control" name="username" required>
    </div>

    <div class="mb-3">
      <label>Display name</label>
      <input class="form-control" name="display_name">
    </div>

    <div class="mb-3">
      <label>Email</label>
      <input class="form-control" name="email" type="email">
    </div>

    <div class="mb-3">
      <label>Password</label>
      <input class="form-control" name="password" type="password" required>
    </div>

    <div class="mb-3">
      <label>Profile image</label>
      <input class="form-control" name="profile_image" type="file" accept="image/*">
    </div>

    <div class="mb-3 form-check">
      <input class="form-check-input" type="checkbox" name="is_super" id="is_super" value="1">
      <label class="form-check-label" for="is_super">Make Super Admin</label>
    </div>

    <div class="mb-3">
      <label>Role</label>
      <select class="form-control" name="role" required>
          <option value="admin">Admin</option>
          <option value="orders_manager">Orders Manager</option>
          <option value="inventory_manager">Inventory Manager</option>
          <option value="support">Customer Support</option>
          <option value="viewer">Viewer</option>
      </select>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Create</button>
      <a class="btn btn-secondary" href="dashboard.php#tab-admin-management">Cancel</a>
    </div>

  </form>

</div>
</body></html>
