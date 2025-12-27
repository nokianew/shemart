<?php
// make_admin_hash.php - run once to create SQL for a demo admin
$plain = 'password';                 // demo password
$hash  = password_hash($plain, PASSWORD_DEFAULT);
$email = 'admin@demo';
$name  = 'Demo Admin';
$role  = 'admin';
$is_super = 1;
$sql = sprintf(
  "INSERT INTO admin_users (username, display_name, email, password_hash, role, is_super) VALUES ('%s','%s','%s','%s','%s',%d);",
  $email, addslashes($name), $email, $hash, $role, $is_super
);
echo "<pre>Run this SQL in your DB:\n\n" . htmlspecialchars($sql) . "\n\nThen delete this file.</pre>";
