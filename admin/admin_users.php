<?php
// admin/admin_users.php
// Manage admin users: list / add / edit / delete
// Drop into womenshop/admin/admin_users.php

require_once __DIR__ . '/../includes/functions.php';
adminRequireUser();
require_once __DIR__ . '/../functions.php';
requireAdmin();

$page_title = "Admin Users";
require_once __DIR__ . '/_admin_header.php';

// --- ensure admin_users table exists (safe)
try {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(150) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) DEFAULT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    die("Failed to ensure admin_users table: " . $e->getMessage());
}

// --- helpers
function redirect($url) {
    header("Location: $url");
    exit;
}

$errors = [];
$success = '';

// Handle POST actions: add, edit, delete
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'admin');

    if ($name === '' || $username === '' || $password === '') {
        $errors[] = "Name, username and password are required.";
    } else {
        // check username uniqueness
        $st = $pdo->prepare("SELECT id FROM admin_users WHERE username = :u LIMIT 1");
        $st->execute([':u'=>$username]);
        if ($st->fetch()) {
            $errors[] = "Username already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO admin_users (username,name,email,password_hash,role) VALUES (:u,:n,:e,:ph,:r)");
            $ins->execute([
                ':u'=>$username, ':n'=>$name, ':e'=>$email, ':ph'=>$hash, ':r'=>$role
            ]);
            $success = "Admin user created.";
        }
    }
}

if ($action === 'edit') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'admin');
    $password = $_POST['password'] ?? '';

    if ($id <= 0 || $name === '') {
        $errors[] = "Invalid input.";
    } else {
        // update fields; update password only if provided
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u = $pdo->prepare("UPDATE admin_users SET name=:n, email=:e, role=:r, password_hash=:ph WHERE id=:id");
            $u->execute([':n'=>$name, ':e'=>$email, ':r'=>$role, ':ph'=>$hash, ':id'=>$id]);
        } else {
            $u = $pdo->prepare("UPDATE admin_users SET name=:n, email=:e, role=:r WHERE id=:id");
            $u->execute([':n'=>$name, ':e'=>$email, ':r'=>$role, ':id'=>$id]);
        }
        $success = "Admin user updated.";
    }
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    // prevent self-delete
    $currentUser = $_SESSION['admin_user']['username'] ?? null;
    if ($id <= 0) {
        $errors[] = "Invalid id.";
    } else {
        $st = $pdo->prepare("SELECT username FROM admin_users WHERE id=:id");
        $st->execute([':id'=>$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $errors[] = "User not found.";
        } elseif ($row['username'] === $currentUser) {
            $errors[] = "You cannot delete the currently logged-in admin.";
        } else {
            $d = $pdo->prepare("DELETE FROM admin_users WHERE id=:id");
            $d->execute([':id'=>$id]);
            $success = "Admin user deleted.";
        }
    }
}

// Fetch all admins
$stmt = $pdo->query("SELECT id, username, name, email, role, created_at FROM admin_users ORDER BY id DESC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Admin Users</h1>
    <a class="btn btn-primary" href="#addForm" onclick="document.getElementById('addForm').scrollIntoView();">+ Add Admin</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?= htmlspecialchars(implode(' | ', $errors)) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <table class="table table-sm">
        <thead>
          <tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $a): ?>
            <tr id="admin-row-<?= intval($a['id']) ?>">
              <td><?= intval($a['id']) ?></td>
              <td><?= htmlspecialchars($a['username']) ?></td>
              <td><?= htmlspecialchars($a['name']) ?></td>
              <td><?= htmlspecialchars($a['email']) ?></td>
              <td><?= htmlspecialchars($a['role']) ?></td>
              <td><?= htmlspecialchars($a['created_at']) ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" onclick="fillEdit(<?= intval($a['id']) ?>,'<?= addslashes(htmlspecialchars($a['username'])) ?>','<?= addslashes(htmlspecialchars($a['name'])) ?>','<?= addslashes(htmlspecialchars($a['email'])) ?>','<?= addslashes(htmlspecialchars($a['role'])) ?>')">Edit</button>
                <form style="display:inline" method="post" onsubmit="return confirm('Delete this admin?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= intval($a['id']) ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($admins)): ?>
            <tr><td colspan="7" class="text-muted">No admin users yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add admin form -->
  <div class="card mb-4" id="addForm">
    <div class="card-header"><strong>Add new admin</strong></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="mb-2">
          <label class="form-label">Name</label>
          <input name="name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Username</label>
          <input name="username" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input name="email" class="form-control" type="email">
        </div>
        <div class="mb-2">
          <label class="form-label">Password</label>
          <input name="password" class="form-control" type="password" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Role</label>
          <select name="role" class="form-control">
            <option value="admin">Admin</option>
            <option value="super">Super Admin</option>
          </select>
        </div>
        <div>
          <button class="btn btn-primary">Create admin</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit admin form -->
  <div class="card mb-4" id="editForm" style="display:none">
    <div class="card-header"><strong>Edit admin</strong></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-2">
          <label class="form-label">Name</label>
          <input id="edit_name" name="name" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input id="edit_email" name="email" class="form-control" type="email">
        </div>
        <div class="mb-2">
          <label class="form-label">New password (leave blank to keep)</label>
          <input id="edit_password" name="password" class="form-control" type="password">
        </div>
        <div class="mb-2">
          <label class="form-label">Role</label>
          <select id="edit_role" name="role" class="form-control">
            <option value="admin">Admin</option>
            <option value="super">Super Admin</option>
          </select>
        </div>
        <div>
          <button class="btn btn-primary">Save changes</button>
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('editForm').style.display='none'">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function fillEdit(id, username, name, email, role) {
  document.getElementById('editForm').style.display = 'block';
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>');
  document.getElementById('edit_email').value = email.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>');
  document.getElementById('edit_role').value = role;
  // scroll into view
  document.getElementById('editForm').scrollIntoView({behavior:'smooth'});
}
</script>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
