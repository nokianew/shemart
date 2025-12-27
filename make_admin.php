<?php
// make_admin.php - run once to create or reset the admin user

require_once __DIR__ . '/config.php';

$username = 'sunnyk';      // admin username
$password = 'admin123';    // admin password

$hash = password_hash($password, PASSWORD_BCRYPT);

// Create or update admin user
$stmt = $pdo->prepare("
    INSERT INTO admin_users (username, password_hash)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)
");

$stmt->execute([$username, $hash]);

echo "Admin user created/updated.<br>";
echo "Username: " . htmlspecialchars($username) . "<br>";
echo "Password: " . htmlspecialchars($password) . "<br>";
