<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

$BASE_DIR = dirname(__DIR__);
require_once $BASE_DIR . "../vendor/vendor/autoload.php";

header('Content-Type: application/json');

// Allow specific prod origins
$strictAllowed = [
  'https://beige-jaguar-560051.hostingersite.com',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allow = false;

// Allow any localhost / 127.0.0.1 (any port) for dev
if (preg_match('#^http://localhost(:\d+)?$#', $origin)) {
  $allow = true;
} elseif (preg_match('#^http://127\.0\.0\.1(:\d+)?$#', $origin)) {
  $allow = true;
} elseif (preg_match('#^https://localhost(:\d+)?$#', $origin)) {
  $allow = true;
} elseif (preg_match('#^https://127\.0\.0\.1(:\d+)?$#', $origin)) {
  $allow = true;
} elseif (in_array($origin, $strictAllowed, true)) {
  $allow = true;
}

if ($allow) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
} else {
  // Uncomment to hard-block unknown origins in prod
  // http_response_code(403);
  // echo json_encode(["status"=>false,"message"=>"Origin not allowed"]);
  // exit;
}

// Enhanced CORS headers for Flutter Web
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, Access-Control-Request-Method, Access-Control-Request-Headers");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Max-Age: 86400"); // cache preflight 24h
header("Access-Control-Allow-Credentials: true"); // For future authentication
header("Access-Control-Expose-Headers: Content-Length, X-JSON"); // Expose additional headers

// Additional security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST','GET'])) {
  http_response_code(405);
  echo json_encode([
    "status"  => false,
    "message" => "Only POST/GET requests allowed",
    "code"    => "METHOD_NOT_ALLOWED"
  ]);
  exit;
}


// Add request logging for debugging (remove in production)
error_log("API Request from: " . $origin . " - Method: " . $_SERVER['REQUEST_METHOD']);

// Include database connection
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_token/jwt_helper.php';
require_once __DIR__ . '/auth/auth_middleware.php';
require_once __DIR__ . '/helpers/email_helper.php';
require_once __DIR__ . '/helpers/otp_helper.php';

?>
