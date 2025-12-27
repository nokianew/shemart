<?php
// Use centralized admin auth (do NOT use session_start()/session_name() here)
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser();

// Optional: require a role (future use)
// requireRole('admin'); // uncomment when role-based permissions implemented

// admin_upload_category_icon.php
declare(strict_types=1);
require_once __DIR__ . '/config.php'; // must provide $pdo and session/login checks

// Simple admin check (remove or replace with your real auth)



$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['icon']) && isset($_POST['category_id'])) {
    $cid = (int)$_POST['category_id'];
    $file = $_FILES['icon'];

    // Basic validation
    $allowed = ['image/png','image/jpeg','image/webp','image/svg+xml'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = "Upload failed (error {$file['error']}).";
    } elseif (!in_array($file['type'], $allowed)) {
        $msg = "Invalid file type.";
    } else {
        // Normalize filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe = strtolower(preg_replace('/[^a-z0-9\-]+/','-',pathinfo($file['name'], PATHINFO_FILENAME)));
        $filename = $safe . '-' . time() . '.' . $ext;
        $target = __DIR__ . '/assets/images/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $msg = "Could not save file.";
        } else {
            // Update DB
            $stmt = $pdo->prepare("UPDATE categories SET icon = ? WHERE id = ?");
            $stmt->execute([$filename, $cid]);
            $msg = "Icon uploaded and category updated: $filename";
        }
    }
}

// Fetch categories for select
$cats = $pdo->query("SELECT id, name, slug, icon FROM categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Upload Category Icon</title></head>
<body>
<h2>Upload Category Icon</h2>
<?php if ($msg): ?><p><strong><?=htmlspecialchars($msg)?></strong></p><?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label>Category:
    <select name="category_id" required>
      <?php foreach ($cats as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['icon'] ?? 'no icon') ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>
  <br><br>
  <label>Icon (PNG/JPEG/WebP/SVG): <input type="file" name="icon" accept="image/png,image/jpeg,image/webp,image/svg+xml" required></label>
  <br><br>
  <button type="submit">Upload & Assign</button>
</form>

<p><a href="index.php">Back to site</a></p>
</body>
</html>
