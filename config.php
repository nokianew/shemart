<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Database connection using Railway MYSQL_URL (PDO)
 * Compatible with existing functions.php
 */

$databaseUrl = getenv('MYSQL_URL');

if (!$databaseUrl) {
    die('MYSQL_URL not set');
}

$db = parse_url($databaseUrl);

$host = $db['host'];
$port = $db['port'] ?? 3306;
$user = $db['user'];
$pass = $db['pass'] ?? '';
$name = ltrim($db['path'], '/');

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $GLOBALS['pdo'] = $pdo; // <-- ADD THIS LINE
} catch (PDOException $e) {
    die('Database connection failed');
}

// =======================
// WhatsApp integration
// =======================

$whatsapp_number = '916260096745';
$whatsapp_default_message = 'Hi SheMart, I need help with my order.';

$site_name = "Shemart";

// =======================
// Payment (Razorpay)
// =======================

define('RAZORPAY_KEY_ID', 'rzp_test_your_key_here');
define('RAZORPAY_KEY_SECRET', 'your_key_secret_here');

// =======================
// Twilio / WhatsApp (optional)
// =======================

define('TWILIO_ACCOUNT_SID', '');
define('TWILIO_AUTH_TOKEN', '');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+1415xxxxxxx');
