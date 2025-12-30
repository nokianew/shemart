<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Database connection using Railway MYSQL_URL
 * Works ONLY inside Render (correct behavior)
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

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $pass, $name, (int)$port);
$conn->set_charset('utf8mb4');


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
