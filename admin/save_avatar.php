<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
adminRequireUser();

require_once __DIR__ . '/../../includes/functions.php';
$pdo = getDB();

header('Content-Type: application/json');

if (empty($_SESSION['admin'])) {
    echo json_encode(['ok'=>false,'error'=>'Not authenticated']);
    exit;
}

if (empty($_FILES['avatar'])) {
    echo json_encode(['ok'=>false,'error'=>'No file uploaded']);
    exit;
}

$file = $_FILES['avatar'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'error'=>'Upload error']);
    exit;
}

// basic validation
$allowed = ['image/png','image/jpeg','image/jpg','image/webp'];
if (!in_array($file['type'], $allowed)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid file type']);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['ok'=>false,'error'=>'File too large (max 2MB)']);
    exit;
}

// ensure target dir exists
$targetDir = __DIR__ . '/../assets/admin_avatars';
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'admin_' . (time()) . '_' . rand(1000,9999) . '.' . $ext;
$dest = $targetDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok'=>false,'error'=>'Failed to save file']);
    exit;
}

// set URL (adjust path according to your webroot)
$publicUrl = '/assets/admin_avatars/' . $filename;

// optionally update admin record in DB via your function
if (function_exists('update_admin_avatar')) {
    // update_admin_avatar($_SESSION['admin']['id'], $publicUrl);
}

// update session for preview
$_SESSION['admin']['avatar'] = $publicUrl;

echo json_encode(['ok'=>true,'url'=>$publicUrl]);
exit;
