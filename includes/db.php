<?php
// includes/db.php (patched)
// Update DB credentials below if needed
$DB_HOST = '127.0.0.1';
$DB_NAME = 'womenshop';
$DB_USER = 'root';
$DB_PASS = '';
$DB_OPTIONS = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_OPTIONS;
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $DB_OPTIONS);
    }
    return $pdo;
}
