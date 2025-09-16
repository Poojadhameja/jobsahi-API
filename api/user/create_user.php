<?php

// header('Content-Type: application/json');

// // Allow specific prod origins
// $strictAllowed = [
//   'https://beige-jaguar-560051.hostingersite.com',
// ];

// $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// $allow = false;

// // Allow any localhost / 127.0.0.1 (any port) for dev
// if (preg_match('#^http://localhost(:\d+)?$#', $origin)) {
//   $allow = true;
// } elseif (preg_match('#^http://127\.0\.0\.1(:\d+)?$#', $origin)) {
//   $allow = true;
// } elseif (in_array($origin, $strictAllowed, true)) {
//   $allow = true;
// }

// if ($allow) {
//   header("Access-Control-Allow-Origin: $origin");
//   header("Vary: Origin");
// } else {
//   // Uncomment to hard-block unknown origins in prod
//   // http_response_code(403);
//   // echo json_encode(["status"=>false,"message"=>"Origin not allowed"]);
//   // exit;
// }

// // Preflight + headers
// header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin");
// header("Access-Control-Allow-Methods: POST, OPTIONS");
// header("Access-Control-Max-Age: 86400"); // cache preflight 24h

// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//   http_response_code(204);
//   exit;
// }

// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//   http_response_code(405);
//   echo json_encode([
//     "status"  => false,
//     "message" => "Only POST requests allowed",
//     "code"    => "METHOD_NOT_ALLOWED"
//   ]);
//   exit;
// }

include '../CORS.php'; // Handles CORS and method check
// --------- Include your existing helpers (unchanged) ----------
require_once '../helpers/response_helper.php';
require_once '../helpers/rate_limiter.php';

// Apply rate limiting (10 registrations per hour) - unchanged
RateLimiter::apply('create_user', 10, 3600);

// --------- Read and validate JSON body (unchanged logic) ----------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ResponseHelper::error("Invalid JSON data", 400, "INVALID_JSON");
}

// Required fields
$required_fields = ['name', 'email', 'password', 'phone_number'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        echo json_encode([
          "message" => ucfirst($field) . " is required",
          "status"  => false
        ]);
        exit;
    }
}

$name         = trim($data['name']);
$email        = trim($data['email']);
$password     = trim($data['password']);
$phone_number = trim($data['phone_number']);
$role         = isset($data['role']) ? trim($data['role']) : 'student'; // default role
$is_verified  = isset($data['is_verified']) ? (int)$data['is_verified'] : 0; // default 0

// Validate email format (your helper)
ResponseHelper::validateEmail($email);

// Validate password strength (min 6)
ResponseHelper::validatePassword($password, 6);

// Validate phone (basic)
if (!preg_match('/^[0-9+\-\s\(\)]{10,15}$/', $phone_number)) {
    ResponseHelper::validationError(['phone_number' => 'Invalid phone number format']);
}

// Additional length check (keep as you had)
if (strlen($password) < 6) {
    echo json_encode([
      "message" => "Password must be at least 6 characters long",
      "status"  => false
    ]);
    exit;
}

// --------- DB include (unchanged) ----------
include "../db.php"; // must define $conn (mysqli)

// ---- Check duplicate email ----
$check_sql = "SELECT id FROM users WHERE email = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(["message" => "Email already exists", "status" => false]);
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_stmt);
} else {
    ResponseHelper::error("Database prepare failed: " . mysqli_error($conn), 500, "DB_PREPARE_ERROR");
}

// ---- Check duplicate phone ----
$check_phone_sql = "SELECT id FROM users WHERE phone_number = ?";
if ($check_phone_stmt = mysqli_prepare($conn, $check_phone_sql)) {
    mysqli_stmt_bind_param($check_phone_stmt, "s", $phone_number);
    mysqli_stmt_execute($check_phone_stmt);
    $check_phone_result = mysqli_stmt_get_result($check_phone_stmt);

    if (mysqli_num_rows($check_phone_result) > 0) {
        echo json_encode(["message" => "Phone number already exists", "status" => false]);
        mysqli_stmt_close($check_phone_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_phone_stmt);
} else {
    ResponseHelper::error("Database prepare failed: " . mysqli_error($conn), 500, "DB_PREPARE_ERROR");
}

// ---- Hash password and insert ----
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, email, password, phone_number, role, is_verified)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    ResponseHelper::error("Database prepare failed: " . mysqli_error($conn), 500, "DB_PREPARE_ERROR");
}

mysqli_stmt_bind_param($stmt, "sssssi",
    $name, $email, $hashed_password, $phone_number, $role, $is_verified
);

if (mysqli_stmt_execute($stmt)) {
    $user_id = mysqli_insert_id($conn);

    ResponseHelper::success([
        "user_id"     => $user_id,
        "name"        => $name,
        "email"       => $email,
        "role"        => $role,
        "is_verified" => (bool)$is_verified
    ], "User registered successfully", 201);
} else {
    ResponseHelper::error("Registration failed: " . mysqli_error($conn), 500, "REGISTRATION_FAILED");
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
