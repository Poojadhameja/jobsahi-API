<?php
require_once __DIR__ . '/../vendor/vendor/autoload.php';

use Dotenv\Dotenv;

// Custom .env path
$dotenvPath = __DIR__ . '/../routes';
$envFile = $dotenvPath . '/.env.txt';

// Load .env safely
if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable($dotenvPath, '.env.txt');
    $dotenv->load();
} else {
    die("❌ Missing .env file at $envFile");
}

// Database Configuration
$dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbPort = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_DATABASE'] ?? 'u829931622_jobsahi';
$dbUser = $_ENV['DB_USERNAME'] ?? 'u829931622_jobsahi';
$dbPass = $_ENV['DB_PASSWORD'] ?? 'Jobsahi12@123';

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

    echo "✅ Database connected successfully";

} catch (Exception $e) {
    die("❌ DB Connection failed: " . $e->getMessage());
}

// JWT Configuration - Check if constants are not already defined
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'jobsahi');
}
if (!defined('JWT_EXPIRY')) {
    define('JWT_EXPIRY', 25200); // 7 hours
}
if (!defined('JWT_ALGORITHM')) {
    define('JWT_ALGORITHM', 'HS256');
}
if (!defined('JWT_REFRESH_EXPIRY')) {
    define('JWT_REFRESH_EXPIRY', 25200); // 7 hours
}

// File Upload Configuration
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
}
if (!defined('ALLOWED_FILE_TYPES')) {
    define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', __DIR__ . '/uploads/');
}

// API Configuration
if (!defined('API_VERSION')) {
    define('API_VERSION', 'v1');
}
if (!defined('API_NAME')) {
    define('API_NAME', 'JOBSAHI-API');
}

// Response Messages
if (!defined('SUCCESS_MESSAGE')) {
    define('SUCCESS_MESSAGE', 'Operation completed successfully');
}
if (!defined('ERROR_MESSAGE')) {
    define('ERROR_MESSAGE', 'An error occurred');
}
if (!defined('VALIDATION_ERROR')) {
    define('VALIDATION_ERROR', 'Validation failed');
}
if (!defined('UNAUTHORIZED')) {
    define('UNAUTHORIZED', 'Unauthorized access');
}
if (!defined('NOT_FOUND')) {
    define('NOT_FOUND', 'Resource not found');
}

// HTTP Status Codes
if (!defined('HTTP_OK')) {
    define('HTTP_OK', 200);
}
if (!defined('HTTP_CREATED')) {
    define('HTTP_CREATED', 201);
}
if (!defined('HTTP_BAD_REQUEST')) {
    define('HTTP_BAD_REQUEST', 400);
}
if (!defined('HTTP_UNAUTHORIZED')) {
    define('HTTP_UNAUTHORIZED', 401);
}
if (!defined('HTTP_FORBIDDEN')) {
    define('HTTP_FORBIDDEN', 403);
}
if (!defined('HTTP_NOT_FOUND')) {
    define('HTTP_NOT_FOUND', 404);
}
if (!defined('HTTP_METHOD_NOT_ALLOWED')) {
    define('HTTP_METHOD_NOT_ALLOWED', 405);
}
if (!defined('HTTP_INTERNAL_SERVER_ERROR')) {
    define('HTTP_INTERNAL_SERVER_ERROR', 500);
}
?>