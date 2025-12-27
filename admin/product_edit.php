<?php
// --------------------------------------
// Product Edit — RBAC Protected
// --------------------------------------

require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../includes/permissions.php';

// 🔐 HARD BLOCK: Only allowed roles can edit products
requirePermission('products.edit');

require_once __DIR__ . '/../includes/functions.php';

if (!isset($pdo) && function_exists('getDB')) {
    $pdo = getDB();
}

/* ===============================
   LOAD CATEGORIES (NO HTML)
================================ */
if (function_exists('getCategories')) {
    $categories = getCategories();
} else {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ===============================
   DEFAULT PRODUCT
================================ */
$product = [
    'id' => 0,
    'name' => '',
    'description' => '',
    'price' => '',
    'stock' => 0,
    'category_id' => $categories[0]['id'] ?? 0,
    'image' => '',
    'status' => 'active',
];

$errors = [];

/* ===============================
   LOAD PRODUCT / DUPLICATE
================================ */
$id = (int)($_GET['id'] ?? 0);
$duplicateId = (int)($_GET['duplicate_id'] ?? 0);

if ($id > 0 || $duplicateId > 0) {
    $srcId = $id ?: $duplicateId;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$srcId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $product = $row;
        if ($duplicateId > 0) {
            $product['id'] = 0;
            $product['name'] .= ' (Copy)';
        }
    } else {
        $errors[] = 'Product not found.';
    }
}

/* ===============================
   HANDLE POST (NO HTML YET)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product['id'] = (int)$_POST['id'];
    $product['name'] = trim($_POST['name'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['price'] = (float)($_POST['price'] ?? 0);
    $product['stock'] = (int)($_POST['stock'] ?? 0);
    $product['category_id'] = (int)($_POST['category_id'] ?? 0);
    $product['status'] = ($_POST['status'] === 'inactive') ? 'inactive' : 'active';

    if ($product['name'] === '') $errors[] = 'Product name is required.';
    if ($product['category_id'] <= 0) $errors[] = 'Select a valid category.';

    /* IMAGE UPLOAD */
    $newImage = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $errors[] = 'Invalid image type.';
        } else {
            $newImage = time() . '_' . preg_replace('/[^a-z0-9]/i','_',$_FILES['image']['name']);
            move_uploaded_file(
                $_FILES['image']['tmp_name'],
                __DIR__ . '/../assets/images/' . $newImage
            );
        }
    }

    if (!$errors) {
        if ($product['id'] > 0) {
            $stmt = $pdo->prepare(
                "UPDATE products SET name=?, description=?, price=?, stock=?, category_id=?, image=?, status=? WHERE id=?"
            );
            $stmt->execute([
                $product['name'],
                $product['description'],
                $product['price'],
                $product['stock'],
                $product['category_id'],
                $newImage ?? $product['image'],
                $product['status'],
                $product['id']
            ]);
            header("Location: products.php?m=updated");
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO products (name, description, price, stock, category_id, image, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $product['name'],
                $product['description'],
                $product['price'],
                $product['stock'],
                $product['category_id'],
                $newImage,
                $product['status']
            ]);
            header("Location: products.php?m=created");
        }
        exit;
    }
}

/* ===============================
   NOW SAFE TO OUTPUT HTML
================================ */
$page_title = $id ? 'Edit Product' : ($duplicateId ? 'Duplicate Product' : 'Add Product');
require_once __DIR__ . '/_admin_header.php';
?>

<div class="d-flex justify-content-between mb-3">
  <h1><?= htmlspecialchars($page_title) ?></h1>
  <a href="products.php" class="btn btn-secondary btn-sm">Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
  <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

<div class="mb-3">
<label>Name</label>
<input name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>">
</div>

<div class="mb-3">
<label>Category</label>
<select name="category_id" class="form-select">
<?php foreach ($categories as $c): ?>
<option value="<?= $c['id'] ?>" <?= $c['id']==$product['category_id']?'selected':'' ?>>
<?= htmlspecialchars($c['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="row">
<div class="col">
<label>Price</label>
<input type="number" step="0.01" name="price" class="form-control" value="<?= $product['price'] ?>">
</div>
<div class="col">
<label>Stock</label>
<input type="number" name="stock" class="form-control" value="<?= $product['stock'] ?>">
</div>
</div>

<div class="mb-3 mt-3">
<label>Image</label>
<?php if ($product['image']): ?>
<br><img src="../assets/images/<?= htmlspecialchars($product['image']) ?>" width="100">
<?php endif; ?>
<input type="file" name="image" class="form-control mt-2">
</div>

<button class="btn btn-primary">Save</button>
<a href="products.php" class="btn btn-secondary">Cancel</a>
</form>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
