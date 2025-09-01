<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

<<<<<<< HEAD
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
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    // PDO Connection
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
=======
// load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Database Configuration
$dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'] . ";charset=utf8mb4";
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];

try {
    $pdo = new PDO($dsn, $username, $password, [
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
<<<<<<< HEAD

    // MySQLi Connection (Optional)
    $conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    if (!$conn) {
        throw new Exception("MySQLi connection failed: " . mysqli_connect_error());
    }

    echo "✅ Database connected successfully";

=======
    
    // Also create mysqli connection for backward compatibility
    $conn = mysqli_connect($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);
    if (!$conn) {
        throw new Exception("MySQLi connection failed: " . mysqli_connect_error());
    }
    
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
} catch (Exception $e) {
    die("❌ DB Connection failed: " . $e->getMessage());
}

<<<<<<< HEAD
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
=======
// JWT Configuration
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'jobsahi');
define('JWT_EXPIRY', 86400); // 24 hours
define('JWT_REFRESH_EXPIRY', 604800); // 7 days

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// API Configuration
define('API_VERSION', 'v1');
define('API_NAME', 'JOBSAHI-API');

// Response Messages
define('SUCCESS_MESSAGE', 'Operation completed successfully');
define('ERROR_MESSAGE', 'An error occurred');
define('VALIDATION_ERROR', 'Validation failed');
define('UNAUTHORIZED', 'Unauthorized access');
define('NOT_FOUND', 'Resource not found');

// HTTP Status Codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_INTERNAL_SERVER_ERROR', 500);
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
