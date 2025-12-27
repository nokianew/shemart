<?php
// --------------------------------------
// Categories List — RBAC Protected
// --------------------------------------

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/permissions.php';

// 🔐 View categories permission
requirePermission('categories.view');

require_once __DIR__ . '/../includes/functions.php';

if (!isset($pdo) && function_exists('getDB')) {
    $pdo = getDB();
}

/* ------------------ ACTION ------------------ */
$action = $_GET['action'] ?? 'list';

/* ------------------ CSRF ------------------ */
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ------------------ HELPERS ------------------ */
function redirect($url) {
    header("Location: $url");
    exit;
}

/* ------------------ UPLOAD CONFIG ------------------ */
$uploadDir = __DIR__ . '/../assets/images/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

/* ------------------ DEFAULTS ------------------ */
$errors = [];
$messages = [];

$category = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'description' => '',
    'icon' => null
];

/* ------------------ LOAD CATEGORY FOR EDIT ------------------ */
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$category) redirect('categories.php');
}

/* ------------------ HANDLE POST ------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {

        $postAction = $_POST['action'] ?? '';

        /* ---------- CREATE / UPDATE ---------- */
        if (in_array($postAction, ['create','update'])) {

            $name = trim($_POST['name'] ?? '');
            $slug = trim($_POST['slug'] ?? '');
            $desc = trim($_POST['description'] ?? '');

            if ($name === '') $errors[] = 'Category name required';
            if ($slug === '') {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            }

            /* Image upload */
            $icon = null;
            if (!empty($_FILES['icon']['name'])) {
                $mime = mime_content_type($_FILES['icon']['tmp_name']);
                if (!isset($allowedMime[$mime])) {
                    $errors[] = 'Invalid image type';
                } else {
                    $icon = time() . '_' . basename($_FILES['icon']['name']);
                    move_uploaded_file($_FILES['icon']['tmp_name'], $uploadDir . $icon);
                }
            }

            if (!$errors) {
                if ($postAction === 'create') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO categories (name, slug, description, icon, created_at)
                         VALUES (?, ?, ?, ?, NOW())"
                    );
                    $stmt->execute([$name, $slug, $desc, $icon]);
                    redirect('categories.php');
                }

                if ($postAction === 'update') {
                    $id = (int)$_POST['id'];

                    if ($icon) {
                        $stmt = $pdo->prepare(
                            "UPDATE categories SET name=?, slug=?, description=?, icon=? WHERE id=?"
                        );
                        $stmt->execute([$name, $slug, $desc, $icon, $id]);
                    } else {
                        $stmt = $pdo->prepare(
                            "UPDATE categories SET name=?, slug=?, description=? WHERE id=?"
                        );
                        $stmt->execute([$name, $slug, $desc, $id]);
                    }
                    redirect('categories.php');
                }
            }
        }

        /* ---------- DELETE ---------- */
        if ($postAction === 'delete') {
            $id = (int)$_POST['delete_id'];
            $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
            redirect('categories.php');
        }

        /* ---------- BULK DELETE ---------- */
        if ($postAction === 'bulk_delete') {
            foreach ($_POST['selected'] ?? [] as $id) {
                $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$id]);
            }
            redirect('categories.php');
        }
    }
}

/* ------------------ LIST ------------------ */
$categories = $pdo->query(
    "SELECT * FROM categories ORDER BY created_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Categories';
require_once __DIR__ . '/_admin_header.php';
?>

<div class="container mt-4">

<div class="d-flex justify-content-between mb-3">
  <h2>Categories</h2>
  <a href="categories.php?action=add" class="btn btn-primary">+ Add Category</a>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>

<!-- ================= FORM ================= -->
<div class="card p-4">
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <input type="hidden" name="action" value="<?= $action === 'add' ? 'create' : 'update' ?>">
  <?php if ($action === 'edit'): ?>
    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
  <?php endif; ?>

  <div class="mb-3">
    <label>Name</label>
    <input name="name" class="form-control"
           value="<?= htmlspecialchars($category['name']) ?>" required>
  </div>

  <div class="mb-3">
    <label>Slug</label>
    <input name="slug" class="form-control"
           value="<?= htmlspecialchars($category['slug']) ?>">
  </div>

  <div class="mb-3">
    <label>Description</label>
    <textarea name="description" class="form-control"><?= htmlspecialchars($category['description']) ?></textarea>
  </div>

  <div class="mb-3">
    <label>Icon</label>
    <input type="file" name="icon" class="form-control">
    <?php if (!empty($category['icon'])): ?>
      <img src="../assets/images/<?= htmlspecialchars($category['icon']) ?>"
           style="height:60px;margin-top:10px">
    <?php endif; ?>
  </div>

  <button class="btn btn-success">Save</button>
  <a href="categories.php" class="btn btn-secondary">Cancel</a>
</form>
</div>

<?php else: ?>

<!-- ================= LIST ================= -->
<form method="post">
<input type="hidden" name="csrf_token" value="<?= $csrf ?>">
<input type="hidden" name="action" value="bulk_delete">

<table class="table table-striped">
<thead>
<tr>
  <th><input type="checkbox" onclick="document.querySelectorAll('[name*=selected]').forEach(c=>c.checked=this.checked)"></th>
  <th>ID</th>
  <th>Name</th>
  <th>Slug</th>
  <th>Created</th>
  <th>Actions</th>
</tr>
</thead>
<tbody>

<?php foreach ($categories as $c): ?>
<tr>
  <td><input type="checkbox" name="selected[]" value="<?= $c['id'] ?>"></td>
  <td>#<?= $c['id'] ?></td>
  <td>
    <?= htmlspecialchars($c['name']) ?>
    <?php if ($c['icon']): ?>
      <img src="../assets/images/<?= htmlspecialchars($c['icon']) ?>" style="height:28px;margin-left:8px">
    <?php endif; ?>
  </td>
  <td><?= htmlspecialchars($c['slug']) ?></td>
  <td><?= htmlspecialchars($c['created_at']) ?></td>
  <td>
    <a href="categories.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
    <form method="post" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
      <button class="btn btn-sm btn-danger">Delete</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<button class="btn btn-outline-danger">Delete Selected</button>
</form>

<?php endif; ?>

</div>
</body>
</html>
