<?php
// set_admin_password.php - run once then delete
require __DIR__ . '/includes/db.php';
$pdo = getDB();

$username = 'superadmin';
$new_plain = 'Admin1234';

$hash = password_hash($new_plain, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE username = ?");
$stmt->execute([$hash, $username]);

$stmt2 = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ?");
$stmt2->execute([$username]);
$row = $stmt2->fetch();

if ($row) {
    echo "Updated admin id={$row['id']}<br>";
    echo "Stored hash: " . htmlspecialchars($row['password']) . "<br><br>";
    echo "password_verify('Admin1234') => " . 
         (password_verify($new_plain, $row['password']) 
            ? "<b style='color:green'>MATCH</b>" 
            : "<b style='color:red'>NO MATCH</b>"
         );
} else {
    echo "No admin found with username '$username'.";
}
