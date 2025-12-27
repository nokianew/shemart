<?php
// --------------------------------------
// Products Page — RBAC Protected
// --------------------------------------

// Centralized admin auth
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

// RBAC permissions
require_once __DIR__ . '/../includes/permissions.php';

// 🔐 HARD SECURITY CHECK (THIS WAS MISSING)
requirePermission('products.view');

// Project helpers
require_once __DIR__ . '/../includes/functions.php';

// DB connection
if (!isset($pdo) && function_exists('getDB')) {
    $pdo = getDB();
}


// ---------- Page bootstrap ----------
$page_title = "Products";
require_once __DIR__ . '/_admin_header.php';

// Safe helper: get categories (use existing function if present)
if (!function_exists('getCategories')) {
    function getCategoriesFallback(PDO $pdo) {
        try {
            $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    $categories = isset($pdo) ? getCategoriesFallback($pdo) : [];
} else {
    $categories = getCategories();
}

// ---------- Filters from GET ----------
$search = trim((string)($_GET['q'] ?? ''));
$filterCategoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)($_GET['per_page'] ?? 10));
$perPage = $perPage > 0 ? $perPage : 10;

// ---------- Bulk actions (POST) with CSRF protection ----------
$bulk_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $token = $_POST['csrf_token'] ?? '';
    // Use centralized verify_csrf() if available
    $csrf_ok = function_exists('verify_csrf') ? verify_csrf($token) : (is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? ''));

    if (!$csrf_ok) {
        $bulk_message = '<div class="alert alert-danger mb-3">Invalid CSRF token. Please refresh and try again.</div>';
    } else {
        $bulkAction = $_POST['bulk_action'];
        $ids = $_POST['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $bulk_message = '<div class="alert alert-warning mb-3">Please select at least one product.</div>';
        } elseif (!in_array($bulkAction, ['activate', 'deactivate', 'delete'], true)) {
            $bulk_message = '<div class="alert alert-warning mb-3">Please choose a valid bulk action.</div>';
        } else {
            $ids = array_map('intval', $ids);
            $ids = array_filter($ids, fn($v)=>$v>0);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                try {
                    if ($bulkAction === 'activate') {
                        $stmt = $pdo->prepare("UPDATE products SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($ids);
                        $bulk_message = '<div class="alert alert-success mb-3">Selected products marked as <strong>Active</strong>.</div>';
                    } elseif ($bulkAction === 'deactivate') {
                        $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id IN ($placeholders)");
                        $stmt->execute($ids);
                        $bulk_message = '<div class="alert alert-success mb-3">Selected products marked as <strong>Inactive</strong>.</div>';
                    } elseif ($bulkAction === 'delete') {
                        // optionally delete related images/files here if needed
                        $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
                        $stmt->execute($ids);
                        $bulk_message = '<div class="alert alert-success mb-3">Selected products have been <strong>deleted</strong>.</div>';
                    }
                } catch (Exception $e) {
                    $bulk_message = '<div class="alert alert-danger mb-3">Bulk action failed. Please try again.</div>';
                }
            } else {
                $bulk_message = '<div class="alert alert-warning mb-3">No valid product IDs selected.</div>';
            }
        }
    }
}

// ---------- Build filter conditions ----------
$whereParts = [];
$params = [];

// Search by name or description
if ($search !== '') {
    $whereParts[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

// Filter by category
if ($filterCategoryId > 0) {
    $whereParts[] = "p.category_id = ?";
    $params[] = $filterCategoryId;
}

$whereSql = '';
if (!empty($whereParts)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereParts);
}

// ---------- Count total for pagination ----------
$countSql = "
    SELECT COUNT(*) 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereSql
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalProducts = (int) $stmtCount->fetchColumn();

$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// ---------- Fetch products with limit ----------
$listSql = "
    SELECT p.*, c.name AS category_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $whereSql
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($listSql);

// bind params for where (indexed)
$idx = 1;
foreach ($params as $pr) {
    $stmt->bindValue($idx++, $pr);
}
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Helper to keep query string for pagination ----------
$queryParams = [];
if ($search !== '') $queryParams['q'] = $search;
if ($filterCategoryId > 0) $queryParams['category_id'] = $filterCategoryId;
$baseQueryString = http_build_query($queryParams);
$baseUrl = 'products.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-0">Products</h1>
    <p class="text-muted mb-0 small">Manage all products listed in your SheMart store.</p>
  </div>
  <a href="product_edit.php" class="btn btn-primary">+ Add Product</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">

    <!-- Filters row (GET) -->
    <form method="get" class="row g-2 mb-3">
      <div class="col-md-4">
        <input type="text"
               name="q"
               class="form-control"
               placeholder="Search by name or description..."
               value="<?= htmlspecialchars($search); ?>">
      </div>

      <div class="col-md-3">
        <select name="category_id" class="form-select">
          <option value="0">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id']; ?>" <?= $filterCategoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($cat['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary flex-grow-1">Filter</button>
        <a href="products.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>

    <!-- Bulk action feedback -->
    <?= $bulk_message; ?>

    <?php if (empty($products)): ?>
      <p class="text-muted mb-0">No products found. Try changing the search or filters.</p>
    <?php else: ?>

      <!-- Bulk actions + table (POST) -->
      <form method="post">
        <!-- CSRF token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()); ?>">
        <!-- preserve filters on bulk post -->
        <input type="hidden" name="q" value="<?= htmlspecialchars($search); ?>">
        <input type="hidden" name="category_id" value="<?= (int)$filterCategoryId; ?>">
        <input type="hidden" name="page" value="<?= (int)$page; ?>">

        <div class="row g-2 mb-2">
          <div class="col-md-3">
            <select name="bulk_action" class="form-select">
              <option value="">Bulk actions</option>
              <option value="activate">Mark as Active</option>
              <option value="deactivate">Mark as Inactive</option>
              <option value="delete">Delete</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-outline-secondary">Apply</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead>
              <tr>
                <th style="width: 30px;">
                  <input type="checkbox" id="select-all">
                </th>
                <th style="width: 60px;">ID</th>
                <th style="width: 70px;">Image</th>
                <th>Name</th>
                <th style="width: 160px;">Category</th>
                <th style="width: 120px;">Price</th>
                <th style="width: 80px;">Stock</th>
                <th style="width: 100px;">Status</th>
                <th style="width: 110px;">Created</th>
                <th style="width: 140px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id']; ?>"></td>
                  <td>#<?= (int)$p['id']; ?></td>
                  <td>
                    <?php if (!empty($p['image'])): ?>
                      <img src="../assets/images/<?= htmlspecialchars($p['image']); ?>" alt="" style="width:50px;height:50px;object-fit:cover;" class="rounded">
                    <?php else: ?>
                      <span class="text-muted small">No image</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($p['name']); ?></div>
                    <div class="small text-muted text-truncate" style="max-width: 260px;">
                      <?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 80)); ?><?php if (mb_strlen($p['description'] ?? '') > 80): ?>…<?php endif; ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($p['category_name'] ?? '—'); ?></td>
                  <td>₹<?= number_format((float)($p['price'] ?? 0), 2); ?></td>
                  <td>
                    <?= (int)($p['stock'] ?? 0); ?>
                    <?php if ((int)($p['stock'] ?? 0) <= 5): ?>
                      <span class="badge bg-warning text-dark ms-1">Low</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (($p['status'] ?? 'active') === 'active'): ?>
                      <span class="badge bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($p['created_at'])): ?>
                      <span class="small"><?= htmlspecialchars($p['created_at']); ?></span>
                    <?php else: ?>
                      <span class="text-muted small">–</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a href="product_edit.php?id=<?= (int)$p['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <a href="product_edit.php?duplicate_id=<?= (int)$p['id']; ?>" class="btn btn-sm btn-outline-success ms-1">Duplicate</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </form>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
          <ul class="pagination pagination-sm mb-0">
            <?php
              $baseForPage = $baseUrl;
              if ($baseQueryString !== '') {
                  $baseForPage .= '?' . $baseQueryString . '&page=';
              } else {
                  $baseForPage .= '?page=';
              }
            ?>

            <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?= $page > 1 ? $baseForPage . ($page - 1) : '#'; ?>">&laquo;</a>
            </li>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="<?= $baseForPage . $i; ?>"><?= $i; ?></a>
              </li>
            <?php endfor; ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?= $page < $totalPages ? $baseForPage . ($page + 1) : '#'; ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<!-- Select-all script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var selectAll = document.getElementById('select-all');
  if (!selectAll) return;

  selectAll.addEventListener('change', function () {
    var checkboxes = document.querySelectorAll('input[name="ids[]"]');
    checkboxes.forEach(function (cb) {
      cb.checked = selectAll.checked;
    });
  });
});
</script>

</div></body></html>
