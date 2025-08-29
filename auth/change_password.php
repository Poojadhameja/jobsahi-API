<?php
// change_password.php - Change user password
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Check if request method is PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(array("message" => "Only PUT requests allowed", "status" => false));
    exit;
}

require_once '../jwt_token/jwt_helper.php';
include "../database/config.php";

// Get JWT token from Authorization header
$jwt_token = JWTHelper::getJWTFromHeader();

if (!$jwt_token) {
    http_response_code(401);
    echo json_encode(array("message" => "Authorization token required", "status" => false));
    exit;
}

// Verify JWT token
$decoded_token = JWTHelper::validateJWT($jwt_token, JWT_SECRET);

if (!$decoded_token) {
    http_response_code(401);
    echo json_encode(array("message" => "Invalid or expired token", "status" => false));
    exit;
}

$user_id = $decoded_token['user_id'];

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

// Validate required fields
if (!isset($data['current_password']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Current password and new password are required", "status" => false));
    exit;
}

$current_password = trim($data['current_password']);
$new_password = trim($data['new_password']);

if (empty($current_password) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(array("message" => "Current password and new password cannot be empty", "status" => false));
    exit;
}

// Validate new password strength (optional - adjust criteria as needed)
if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(array("message" => "New password must be at least 6 characters long", "status" => false));
    exit;
}

// Check if new password is same as current password
if ($current_password === $new_password) {
    http_response_code(400);
    echo json_encode(array("message" => "New password must be different from current password", "status" => false));
    exit;
}

// Fetch current user and verify current password
$user_sql = "SELECT id, password FROM users WHERE id = ?";

if ($user_stmt = mysqli_prepare($conn, $user_sql)) {
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    
    if (mysqli_num_rows($user_result) === 0) {
        http_response_code(404);
        echo json_encode(array("message" => "User not found", "status" => false));
        mysqli_stmt_close($user_stmt);
        mysqli_close($conn);
        exit;
    }
    
    $user_data = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    // Verify current password
    if (!password_verify($current_password, $user_data['password'])) {
        http_response_code(401);
        echo json_encode(array("message" => "Current password is incorrect", "status" => false));
        mysqli_close($conn);
        exit;
    }
    
    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
    
    if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "si", $new_password_hash, $user_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            http_response_code(200);
            echo json_encode(array(
                "message" => "Password changed successfully",
                "status" => true
            ));
        } else {
            http_response_code(500);
            echo json_encode(array("message" => "Failed to update password", "status" => false));
        }
        
        mysqli_stmt_close($update_stmt);
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Database prepare failed: " . mysqli_error($conn), "status" => false));
    }
    
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed: " . mysqli_error($conn), "status" => false));
}

mysqli_close($conn);
?>