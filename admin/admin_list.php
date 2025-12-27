<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_helpers.php';

require_once __DIR__ . '/../includes/permissions.php'; // ⬅️ ADDED: load RBAC helpers

adminRequireUser();
reload_current_admin($conn);

// Enforce permission: only admins with admins.view can access this page
requirePermission('admins.view');

// ----------------------
// ROLE FILTER HANDLING
// ----------------------
$filter = $_GET['role'] ?? 'all';

$validRoles = [
    'all' => 'All',
    'super' => 'Super Admin',
    'admin' => 'Admin',
    'orders_manager' => 'Orders Manager',
    'inventory_manager' => 'Inventory Manager',
    'support' => 'Support',
    'viewer' => 'Viewer'
];

$where = "";

// apply filter
if ($filter === 'super') {
    $where = "WHERE is_super = 1";
} elseif ($filter !== '' && $filter !== 'all') {
    $safeRole = $conn->real_escape_string($filter);
    $where = "WHERE is_super = 0 AND role = '{$safeRole}'";
}

// final SQL
$sql = "
    SELECT id, username, display_name, email, profile_image, is_super, role, created_at 
    FROM admin_users
    $where
    ORDER BY id DESC
";


$res = $conn->query($sql);
$admins = [];
while ($r = $res->fetch_assoc()) $admins[] = $r;

function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admins</title>
<style>
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 8px; border: 1px solid #ccc; }
    th { background: #eee; }
</style>
</head>
<body>

<h2>Admins</h2>

<p>
    <a href="admin_create.php">Create New Admin</a> |
    <a href="profile.php">My Profile</a>
</p>

<!-- ROLE FILTER DROPDOWN -->
<form method="get" style="margin-bottom: 15px;">
    <label><strong>Filter by Role:</strong></label>
    <select name="role" onchange="this.form.submit()">
        <?php foreach ($validRoles as $key => $label): ?>
            <option value="<?= esc($key) ?>" <?= ($filter === $key ? 'selected' : '') ?>>
                <?= esc($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><button type="submit">Apply</button></noscript>
</form>

<!-- ADMIN TABLE -->
<table>
<tr>
    <th>Avatar</th>
    <th>Username</th>
    <th>Display Name</th>
    <th>Email</th>
    <th>Role</th>
    <th>Created</th>
    <th>Actions</th>
</tr>

<?php foreach ($admins as $a): ?>
<tr>

    <td>
        <img src="../assets/admin_profile/<?= esc($a['profile_image'] ?: 'default.png') ?>" 
             width="48" height="48" style="border-radius:5px;">
    </td>

    <td><?= esc($a['username']) ?></td>
    <td><?= esc($a['display_name']) ?></td>
    <td><?= esc($a['email']) ?></td>

    <td>
        <?php
            if ((int)$a['is_super'] === 1) {
                echo "Super Admin";
            } else {
                echo esc(ucwords(str_replace('_', ' ', $a['role'] ?? 'admin')));
            }
        ?>
    </td>

    <td><?= esc($a['created_at']) ?></td>

    <td>
        <a href="admin_edit.php?id=<?= intval($a['id']) ?>">Edit</a>

        <?php if (current_admin_is_super() && intval($_SESSION['admin']['id']) !== intval($a['id'])): ?>
            | <a href="admin_delete.php?id=<?= intval($a['id']) ?>" 
                 onclick="return confirm('Delete this admin?');">Delete</a>
        <?php endif; ?>
    </td>

</tr>
<?php endforeach; ?>

</table>

</body>
</html>
