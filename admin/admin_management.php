<?php
// admin/admin_management.php
// Super Admin — Admin Management (Polished UI)

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

// Only super admin allowed
if (empty($_SESSION['admin']['is_super'])) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

$page_title = "Admin Management";
require_once __DIR__ . '/_admin_header.php';

$pdo = getDB();

function esc($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// Fetch admins
$admins = [];
try {
    $st = $pdo->query("
        SELECT id, username, display_name, email, role, is_super, created_at
        FROM admin_users
        ORDER BY id DESC
    ");
    $admins = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $admins = [];
}
?>

<div class="container-fluid mt-3">

  <!-- PAGE HEADER -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="h4 mb-0">Admin Management</h1>
      <p class="text-muted small mb-0">Create, edit and manage admin users</p>
    </div>

    <a href="admin_create.php" class="btn btn-primary btn-sm">
      + Create New Admin
    </a>
  </div>

  <!-- ADMIN TABLE -->
  <div class="card shadow-sm border-0">
    <div class="card-body table-responsive p-0">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Display</th>
            <th>Email</th>
            <th>Role</th>
            <th>Super</th>
            <th>Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>

        <?php if (empty($admins)): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              No admin users found.
            </td>
          </tr>
        <?php else: foreach ($admins as $a): ?>
          <tr>
            <td><?= (int)$a['id'] ?></td>
            <td><?= esc($a['username']) ?></td>
            <td><?= esc($a['display_name'] ?: '-') ?></td>
            <td><?= esc($a['email'] ?: '-') ?></td>

            <td>
              <span class="badge bg-secondary">
                <?= esc($a['role'] ?? 'admin') ?>
              </span>
            </td>

            <td>
              <?php if ((int)$a['is_super']): ?>
                <span class="badge bg-success">Yes</span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>

            <td class="text-muted small">
              <?= esc($a['created_at']) ?>
            </td>

            <td class="text-end">
              <a href="admin_edit.php?id=<?= (int)$a['id'] ?>"
                 class="btn btn-sm btn-outline-primary">
                Edit
              </a>

              <?php if ((int)$a['id'] !== (int)($_SESSION['admin']['id'] ?? 0)): ?>
                <a href="admin_delete.php?id=<?= (int)$a['id'] ?>"
                   class="btn btn-sm btn-outline-danger ms-1"
                   onclick="return confirm('Are you sure you want to delete this admin?');">
                  Delete
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>

        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
