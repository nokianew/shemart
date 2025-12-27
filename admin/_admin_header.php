<?php
/**
 * _admin_header.php
 * Clean admin header with integrated AJAX profile/settings panel.
 * Location: /xampp/htdocs/womenshop/admin/_admin_header.php
 */

// 1. Core Includes & Authentication
require_once __DIR__ . '/../includes/functions.php';

if (file_exists(__DIR__ . '/../includes/admin_auth.php')) {
    require_once __DIR__ . '/../includes/admin_auth.php';
    if (function_exists('adminRequireUser')) {
        adminRequireUser();
    }
} else {
    if (function_exists('adminRequireUser')) {
        adminRequireUser();
    }
}

// 2. Page Configuration
if (!isset($page_title)) { $page_title = "Admin"; }

// Prepare admin data from session (with safe fallbacks)
$admin = $admin ?? [
  'display_name' => $_SESSION['admin']['display_name'] ?? ($_SESSION['admin']['username'] ?? 'Admin User'),
  'role'         => $_SESSION['admin']['role'] ?? 'Admin',
  'email'        => $_SESSION['admin']['email'] ?? 'admin@example.com',
  'avatar'       => $_SESSION['admin']['avatar'] ?? null,
  'is_super'     => $_SESSION['admin']['is_super'] ?? false,
];

// Ensure CSRF token for security
$csrf_token = $csrf_token ?? ($_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)));

// Helper for initials if no avatar exists
function _admin_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach(array_slice($parts, 0, 2) as $p) {
        $initials .= strtoupper($p[0] ?? '');
    }
    return $initials ?: 'A';
}

$avatar_src = !empty($admin['avatar']) ? htmlspecialchars($admin['avatar']) : '/assets/img/default-avatar.svg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin – <?= htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* PROFILE PANEL STYLES */
        .profile-root { position: relative; display: inline-block; }
        .profile-trigger { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; padding: 6px 10px; border-radius: 8px; color: #fff; }
        .profile-trigger .avatar-sm { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #eef2f7; font-weight: 700; color: #1f2937; }
        
        .admin-panel { position: absolute; right: 0; top: calc(100% + 10px); width: 460px; background: #fff; border-radius: 10px; box-shadow: 0 20px 50px rgba(2,6,23,0.18); border: 1px solid rgba(0,0,0,0.06); z-index: 1400; display: none; }
        .admin-panel.open { display: block; animation: fadeIn 0.12s ease-out; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        
        .admin-panel .panel-inner { padding: 14px; }
        .top-row { display: flex; gap: 12px; align-items: center; padding-bottom: 6px; }
        .avatar-lg { width: 64px; height: 64px; border-radius: 10px; object-fit: cover; background: #f3f7fb; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #0f172a; }
        .name-block .display-name { font-weight: 700; font-size: 15px; color: #0f172a; }
        .name-block .role { font-size: 12px; color: #6b7280; margin-top: 2px; }
        
        /* TABS */
        .tabs { margin-top: 10px; border-top: 1px solid #f3f4f6; display: flex; gap: 6px; padding-top: 10px; align-items: center; }
        .tab-btn { padding: 6px 10px; border-radius: 8px; cursor: pointer; font-size: 13px; color: #374151; background: transparent; border: 1px solid transparent; }
        .tab-btn.active { background: #f8fafc; border-color: #e2e8f0; font-weight: 600; }
        
        /* FORMS */
        .tab-content { margin-top: 10px; color: #333; }
        .field { margin-bottom: 10px; display: flex; flex-direction: column; }
        .field label { font-size: 13px; color: #374151; margin-bottom: 4px; font-weight: 500; }
        .field input { padding: 8px 10px; border: 1px solid #e6e9ee; border-radius: 8px; font-size: 14px; }
        
        .actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; }
        .btn-mini { padding: 8px 14px; border-radius: 8px; background: #0f172a; color: #fff; border: 0; cursor: pointer; font-weight: 600; font-size: 13px; }
        .btn-ghost { background: #fff; border: 1px solid #e2e8f0; color: #475569; padding: 8px 14px; border-radius: 8px; font-size: 13px; }
        
        .logout { margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 10px; display: flex; justify-content: flex-end; }
        .logout-btn { background: transparent; border: 1px solid #fecaca; color: #dc2626; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 13px; transition: 0.2s; }
        .logout-btn:hover { background: #fef2f2; }
        
        .avatar-preview { width: 96px; height: 96px; border-radius: 10px; object-fit: cover; background: #f3f7fb; display: block; margin: 0 auto 8px; border: 1px solid #e2e8f0; }
        .muted { font-size: 12px; color: #64748b; }

        @media (max-width: 520px) { .admin-panel { right: 6px; left: 6px; width: auto; } }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm">
  <div class="container d-flex justify-content-between align-items-center">
    <a class="navbar-brand fw-bold" href="dashboard.php">SheMart Admin</a>

    <div class="profile-root" id="profileRoot">
        <div class="profile-trigger" id="profileTrigger">
          <div class="avatar-sm" id="headerAvatar">
            <?php if(!empty($admin['avatar'])): ?>
              <img src="<?= $avatar_src ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <span><?= _admin_initials($admin['display_name']) ?></span>
            <?php endif; ?>
          </div>
          <div class="d-none d-sm-flex flex-column">
            <span style="font-size:13px; font-weight:700; color:#fff"><?= htmlspecialchars($admin['display_name']) ?></span>
            <small style="color:#cbd5e1; font-size:11px"><?= htmlspecialchars($admin['role']) ?></small>
          </div>
        </div>

        <div class="admin-panel" id="adminPanel">
          <div class="panel-inner">
            <div class="top-row">
              <div style="width:78px;">
                <?php if(!empty($admin['avatar'])): ?>
                  <img id="panelAvatar" class="avatar-lg" src="<?= $avatar_src ?>" alt="avatar">
                <?php else: ?>
                  <div id="panelAvatar" class="avatar-lg"><?= _admin_initials($admin['display_name']) ?></div>
                <?php endif; ?>
              </div>
              <div class="name-block" style="flex:1;">
                <div class="display-name" id="panelDisplayName"><?= htmlspecialchars($admin['display_name']) ?></div>
                <div class="role" id="panelRole">Access Level: <?= htmlspecialchars($admin['role']) ?></div>
                <div class="muted mt-1" id="panelEmail"><?= htmlspecialchars($admin['email']) ?></div>
              </div>
            </div>

            <div class="tabs">
              <button class="tab-btn active" data-tab="profileTab">Profile</button>
              <button class="tab-btn" data-tab="settingsTab">Security</button>
            </div>

            <div class="tab-content">
              <div id="profileTab" class="tab-panel">
                <div class="field">
                  <label>Display name</label>
                  <input type="text" id="displayNameInline" value="<?= htmlspecialchars($admin['display_name']) ?>">
                </div>
                <div class="field">
                  <label>Email Address</label>
                  <input type="email" value="<?= htmlspecialchars($admin['email']) ?>" disabled style="background:#f8fafc">
                </div>
                <div class="actions">
                  <button class="btn-mini" id="saveInline">Save Name</button>
                </div>
              </div>

              <div id="settingsTab" class="tab-panel" style="display:none;">
                <form id="settingsForm">
                  <input type="hidden" id="settingsCsrf" value="<?= htmlspecialchars($csrf_token) ?>">
                  <div class="row g-3">
                    <div class="col-4 text-center">
                      <img id="settingsAvatarPreview" class="avatar-preview" src="<?= $avatar_src ?>" alt="preview">
                      <label class="btn btn-sm btn-outline-secondary w-100" for="avatarFile" style="font-size:11px">Change Photo</label>
                      <input type="file" id="avatarFile" accept="image/*" style="display:none;">
                      <button id="uploadAvatarBtn" class="btn btn-sm btn-dark w-100 mt-2" type="button" style="font-size:11px">Upload</button>
                    </div>

                    <div class="col-8">
                      <div class="field">
                        <label>Update Email</label>
                        <input id="settingsEmail" type="email" value="<?= htmlspecialchars($admin['email']) ?>">
                      </div>
                      <div class="field">
                        <label>New Password</label>
                        <input id="newPassword" type="password" placeholder="Min 8 characters">
                      </div>
                      <div class="actions">
                        <button class="btn-mini" id="saveSettings" type="button">Update Account</button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <div class="logout">
              <button class="logout-btn" onclick="window.location.href='logout.php'">Sign Out</button>
            </div>
          </div>
        </div>
    </div>
  </div>
</nav>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4 border-bottom">
  <div class="container">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
        <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mb-5">
<script>
/**
 * Admin Panel Interaction Script
 */
document.addEventListener('DOMContentLoaded', function() {
  const root = document.getElementById('profileRoot');
  const trigger = document.getElementById('profileTrigger');
  const panel = document.getElementById('adminPanel');
  
  // Toggle Logic
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    panel.classList.toggle('open');
  });

  document.addEventListener('click', (e) => {
    if (!root.contains(e.target)) panel.classList.remove('open');
  });

  // Tab Logic
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabPanels = document.querySelectorAll('.tab-panel');

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const target = btn.dataset.tab;
      tabPanels.forEach(p => p.style.display = (p.id === target ? 'block' : 'none'));
    });
  });

  // AJAX: Update Name
  document.getElementById('saveInline').addEventListener('click', async (e) => {
    const nameInput = document.getElementById('displayNameInline');
    const name = nameInput.value.trim();
    if(!name) return alert('Name required');

    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('display_name', name);
    fd.append('csrf_token', '<?= $csrf_token ?>');

    try {
      const res = await fetch('profile.php', { 
        method: 'POST', 
        body: fd, 
        headers: { 'X-Requested-With': 'XMLHttpRequest' } 
      });
      const data = await res.json();
      if(data.ok) {
        document.getElementById('panelDisplayName').textContent = name;
        trigger.querySelector('span').textContent = name;
        alert('Name updated successfully');
      } else {
        alert(data.error || 'Update failed');
      }
    } catch (err) { alert('Server error'); }
  });

  // Avatar Preview
  const avatarFile = document.getElementById('avatarFile');
  const avatarPreview = document.getElementById('settingsAvatarPreview');
  
  avatarFile.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => avatarPreview.src = e.target.result;
      reader.readAsDataURL(file);
    }
  });

  // AJAX: Upload Avatar
  document.getElementById('uploadAvatarBtn').addEventListener('click', async () => {
    const file = avatarFile.files[0];
    if (!file) return alert('Please select a file first.');

    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('csrf_token', '<?= $csrf_token ?>');

    try {
      const res = await fetch('ajax/save_avatar.php', { method: 'POST',body: fd, credentials: 'same-origin'});
      const data = await res.json();
      if(data.ok) {
        location.reload(); // Simplest way to sync all avatar instances
      } else {
        alert(data.error || 'Upload failed');
      }
    } catch (err) { alert('Upload error'); }
  });

  // AJAX: Save Security Settings
  document.getElementById('saveSettings').addEventListener('click', async () => {
    const email = document.getElementById('settingsEmail').value;
    const password = document.getElementById('newPassword').value;

    const fd = new FormData();
    fd.append('action', 'update_settings');
    fd.append('email', email);
    fd.append('password', password);
    fd.append('csrf_token', '<?= $csrf_token ?>');

    try {
      const res = await fetch('profile.php', { 
        method: 'POST', 
        body: fd, 
        headers: { 'X-Requested-With': 'XMLHttpRequest' } 
      });
      const data = await res.json();
      if(data.ok) {
        document.getElementById('panelEmail').textContent = email;
        document.getElementById('newPassword').value = '';
        alert('Settings updated');
      } else {
        alert(data.error || 'Update failed');
      }
    } catch (err) { alert('Server error'); }
  });
});
</script>
