<?php
// RBAC Debug Page
require_once __DIR__ . '/../includes/admin_auth.php';
adminRequireUser(); // ensure logged in admin
require_once __DIR__ . '/../includes/permissions.php';

echo "<pre>";

echo "===== ADMIN SESSION =====\n";
echo "Admin ID: " . (currentAdminId() ?? 'none') . "\n";

$admin = currentAdmin();
echo "Admin Row:\n";
print_r($admin);

echo "\n===== ROLES =====\n";
print_r($_SESSION['admin_roles'] ?? []);

echo "\n===== PERMISSIONS =====\n";
print_r($_SESSION['admin_permissions'] ?? []);

echo "\n===== TEST HELPERS =====\n";
echo "Has role 'admin'? ";
var_export(adminHasRole('admin'));
echo "\n";

echo "Can manage orders? ";
var_export(can('orders.manage'));
echo "\n";

echo "Can update order status? ";
var_export(can('orders.update_status'));
echo "\n";

echo "\n===== RAW SESSION =====\n";
print_r($_SESSION);

echo "</pre>";
