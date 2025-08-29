<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Database Configuration
$dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_DATABASE'] . ";charset=utf8mb4";
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Also create mysqli connection for backward compatibility
    $conn = mysqli_connect($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE']);
    if (!$conn) {
        throw new Exception("MySQLi connection failed: " . mysqli_connect_error());
    }
    
} catch (Exception $e) {
    die("❌ DB Connection failed: " . $e->getMessage());
}

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
