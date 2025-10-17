<?php
$BASE_DIR = dirname(__DIR__);
require_once $BASE_DIR . "../vendor/vendor/autoload.php";

// Database Configuration
$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'jobsahi_database';
$dbUser = 'root';
$dbPass = '';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    // PDO Connection
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // MySQLi Connection (Optional)
    $conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    if (!$conn) {
        throw new Exception("MySQLi connection failed: " . mysqli_connect_error());
    }

} catch (Exception $e) {
    die("❌ DB Connection failed: " . $e->getMessage());
}

// JWT Configuration - Required by API files
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', 'jobsahi'); // Use a strong, unique key in production
}
if (!defined('JWT_ALGORITHM')) {
    define('JWT_ALGORITHM', 'HS256');
}
if (!defined('JWT_EXPIRY')) {
    define('JWT_EXPIRY', 3600); // 1 hour in seconds
}
?>