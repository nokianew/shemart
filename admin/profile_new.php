<?php
// File: /womenshop/admin/profile_new.php
session_start();

if (file_exists(__DIR__ . '/../functions.php')) {
    require_once __DIR__ . '/../functions.php';
}

if (empty($_SESSION['admin'])) {
    header('Location: login_new.php');
    exit;
}

$admin = $_SESSION['admin'];
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Profile — SheMart</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--bg:#f6f8fb;--card:#fff;--accent:#0ea5e9;--muted:#6b7280}
body{margin:0;font-family:Inter,system-ui,Arial;background:var(--bg);color:#111}
.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:var(--card);border-radius:10px;box-shadow:0 6px 20px rgba(16,24,40,0.06);padding:22px;width:100%;max-width:900px}
.avatar{width:82px;height:82px;border-radius:50%;background:#eef7fb;border:3px solid #fff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0b5567;font-size:26px}
label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px}
input{width:100%;padding:10px 12px;border:1px solid #e6e9ef;border-radius:8px;font-size:14px}
.btn{background:var(--accent);color:white;padding:10px 14px;border-radius:8px;border:0;cursor:pointer}
.small{font-size:13px;color:var(--muted)}
.actions{display:flex;gap:8px;margin-top:12px}
.success{background:#e8f8f2;color:#065f46;padding:8px;border-radius:6px;display:none}
.error{background:#fff1f2;color:#9f1239;padding:8px;border-radius:6px;display:none}
</style>
</head>

<body>
<div class="wrap">
<div class="card">

<!-- HEADER -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
  <div style="display:flex;gap:12px;align-items:center">
    <div class="avatar" id="avatarPreview">
      <?php
        if (!empty($admin['avatar'])) {
          echo '<img src="'.htmlspecialchars($admin['avatar']).'" style="width:82px;height:82px;border-radius:50%">';
        } else {
          echo htmlspecialchars(strtoupper(substr($admin['name'] ?? 'A',0,1)));
        }
      ?>
    </div>
    <div>
      <h2 style="margin:0"><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></h2>
      <div class="small"><?= htmlspecialchars($admin['email'] ?? '') ?> • <?= htmlspecialchars($admin['role'] ?? 'Admin') ?></div>
    </div>
  </div>
</div>

<div id="successBox" class="success"></div>
<div id="errorBox" class="error"></div>

<!-- PROFILE FORM -->
<form id="profileForm">
  <label>Name</label>
  <input name="name" value="<?= htmlspecialchars($admin['name'] ?? '') ?>">

  <div style="height:12px"></div>

  <label>Email</label>
  <input name="email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>">

  <div class="actions">
    <button class="btn" type="submit">Save changes</button>
  </div>
</form>

</div>
</div>

<script>
document.getElementById('profileForm').addEventListener('submit', function(e){
    e.preventDefault();

    const formData = new FormData(this);
    const successBox = document.getElementById('successBox');
    const errorBox = document.getElementById('errorBox');

    successBox.style.display = 'none';
    errorBox.style.display = 'none';

    fetch('ajax/profile_save.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.ok) {
            successBox.textContent = 'Profile updated successfully';
            successBox.style.display = 'block';
        } else {
            errorBox.textContent = res.error || 'Save failed';
            errorBox.style.display = 'block';
        }
    })
    .catch(() => {
        errorBox.textContent = 'Server error';
        errorBox.style.display = 'block';
    });
});
</script>

</body>
</html>
