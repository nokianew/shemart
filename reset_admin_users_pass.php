<?php
// Make sure DB loads
require_once __DIR__ . '/config.php';  // This MUST match your project structure

// Admin user to reset
$username = 'sunnyk';  // your username in admin_users table
$new_password = 'admin123';

// Hash password
$hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update admin user password
$stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
$stmt->bind_param('ss', $hash, $username);
$stmt->execute();

echo "Password reset successful. New password = $new_password";
