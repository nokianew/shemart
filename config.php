<?php
// config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$host = 'localhost';
$db   = 'womenshop';

// DB credentials — adjust if different
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';        // XAMPP default: empty
$dbname = 'womenshop';

// create mysqli connection and expose $conn
$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($conn->connect_errno) {
    // In development show the error; in production handle this more gracefully
    die('Database connection failed: ' . $conn->connect_error);
}

// set charset
$conn->set_charset('utf8mb4');


// =======================
// WhatsApp integration
// =======================

// Use full number with country code, no + symbol, no spaces
$whatsapp_number = '916260096745';  
$whatsapp_default_message = 'Hi SheMart, I need help with my order.';

$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$site_name = "PawanGenS"; // your brand name


// -----------------------------------
// Payment (Razorpay)
// -----------------------------------
define('RAZORPAY_KEY_ID', 'rzp_test_your_key_here');      
define('RAZORPAY_KEY_SECRET', 'your_key_secret_here');    

// -----------------------------------
// Twilio / WhatsApp (optional)
// -----------------------------------
define('TWILIO_ACCOUNT_SID', '');      
define('TWILIO_AUTH_TOKEN', '');       
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+1415xxxxxxx'); 

?>
