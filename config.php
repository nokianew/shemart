<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =======================
// Database (Railway via Render env vars)
// =======================

$dbhost = $_ENV['DB_HOST'] ?? null;
$dbport = $_ENV['DB_PORT'] ?? 3306;
$dbuser = $_ENV['DB_USER'] ?? null;
$dbpass = $_ENV['DB_PASS'] ?? null;
$dbname = $_ENV['DB_NAME'] ?? null;

if (!$dbhost || !$dbuser || !$dbname) {
    die('Database configuration missing');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(
    $dbhost,
    $dbuser,
    $dbpass,
    $dbname,
    (int)$dbport
);

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
