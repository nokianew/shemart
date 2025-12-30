<?php
require_once __DIR__ . '/../config.php';

echo "DB connected successfully<br>";

$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<pre>";
print_r($tables);
