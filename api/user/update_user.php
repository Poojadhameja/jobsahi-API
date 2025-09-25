<?php
// update_user.php - Update user with JWT authentication
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Include config first to define constants
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("message" => "Only PUT or POST requests allowed", "status" => false));
    exit;
}

// Debug: Check if Authorization header exists
$headers = getallheaders();
if (!isset($headers['Authorization']) && !isset($headers['authorization'])) {
    http_response_code(401);
    echo json_encode(array(
        "message" => "Authorization header missing", 
        "status" => false,
        "debug" => "Please include 'Authorization: Bearer <token>' header"
    ));
    exit;
}

// Authenticate user with error handling
try {
    $current_user = authenticateJWT();
    if (!$current_user) {
        http_response_code(401);
        echo json_encode(array("message" => "Invalid authentication", "status" => false));
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(array(
        "message" => "Authentication failed", 
        "status" => false,
        "debug" => $e->getMessage()
    ));
    exit;
}

$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

// Validate required fields
$required_fields = ['uid', 'uname', 'uemail', 'urole', 'uphone', 'uverified'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(array("message" => "Missing required field: $field", "status" => false));
        exit;
    }
}

$id = intval($data['uid']);
$user_name = trim($data['uname']);
$email = trim($data['uemail']);
$password = isset($data['upassword']) ? trim($data['upassword']) : null;
$role = trim($data['urole']);
$phone_number = trim($data['uphone']);
$is_verified = intval($data['uverified']);

// Debug authorization check
error_log("Current user role: " . $current_user['role']);
error_log("Current user ID: " . $current_user['user_id']);
error_log("Target user ID: " . $id);


// Validate input data
if (empty($name) || empty($email) || empty($role) || empty($phone_number)) {
    http_response_code(400);
    echo json_encode(array("message" => "User_Name, email, role, and phone number cannot be empty", "status" => false));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid email format", "status" => false));
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid user ID", "status" => false));
    exit;
}

// Check if user exists - FIXED: Added missing placeholder
$check_sql = "SELECT id FROM users WHERE id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) == 0) {
        http_response_code(404);
        echo json_encode(array("message" => "User not found", "status" => false));
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_stmt);
}

// Check if email exists for another user
$email_check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
if ($email_check_stmt = mysqli_prepare($conn, $email_check_sql)) {
    mysqli_stmt_bind_param($email_check_stmt, "si", $email, $id);
    mysqli_stmt_execute($email_check_stmt);
    $email_check_result = mysqli_stmt_get_result($email_check_stmt);
    
    if (mysqli_num_rows($email_check_result) > 0) {
        http_response_code(409);
        echo json_encode(array("message" => "Email already exists for another user", "status" => false));
        mysqli_stmt_close($email_check_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($email_check_stmt);
}

// Update query based on password
if (!empty($password)) {
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(array("message" => "Password must be at least 6 characters long", "status" => false));
        exit;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET user_name = ?, email = ?, password = ?, role = ?, phone_number = ?, is_verified = ? WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssssii", $user_name, $email, $hashed_password, $role, $phone_number, $is_verified, $id);
    }
} else {
    $sql = "UPDATE users SET user_name = ?, email = ?, role = ?, phone_number = ?, is_verified = ? WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssssii", $user_name, $email, $role, $phone_number, $is_verified, $id);
    }
}

if (isset($stmt)) {
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        
        if ($affected_rows > 0) {
            http_response_code(200);
            echo json_encode(array(
                "message" => "User updated successfully", 
                "status" => true
            ));
        } else {
            http_response_code(200);
            echo json_encode(array("message" => "No changes were made", "status" => true));
        }
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Failed to update user", "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Failed to prepare update statement", "status" => false));
}

mysqli_close($conn);
?>