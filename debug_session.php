<?php
require_once __DIR__ . '/config.php'; // adjust if needed
if (session_status() === PHP_SESSION_NONE) session_start();
echo "<pre>";
echo "Cookie PHPSESSID: " . ($_COOKIE['PHPSESSID'] ?? 'NONE') . PHP_EOL;
echo "Session ID: " . session_id() . PHP_EOL;
echo "Session contents:\n";
print_r($_SESSION);
echo "</pre>";
