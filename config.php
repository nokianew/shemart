<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Railway MySQL connection using environment variable

$databaseUrl = getenv("MYSQL_URL");

if (!$databaseUrl) {
    die("MYSQL_URL not set");
}

$db = parse_url($databaseUrl);

$dbhost = $db["host"];
$dbport = $db["port"] ?? 3306;
$dbuser = $db["user"];
$dbpass = $db["pass"];
$dbname = ltrim($db["path"], "/");

$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname, $dbport);

if ($conn->connect_errno) {
    die('Database connection failed: ' . $conn->connect_error);
}

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

$site_name = "Shemart"; // your brand name


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
