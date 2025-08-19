<?php
// get_user.php - Get single user by ID
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../auth/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate user
$current_user = authenticateJWT();

// Get user ID from URL parameter or JSON
$user_id = $_GET['uid'] ?? null;
if (!$user_id) {
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id = $data['uid'] ?? null;
}

if (!$user_id) {
    http_response_code(400);
    echo json_encode(array("message" => "User ID is required", "status" => false));
    exit;
}

// Check if user can access this data (own data or admin)
if ($current_user['role'] !== 'admin' && $current_user['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(array("message" => "Access denied", "status" => false));
    exit;
}

include "../config.php";

$sql = "SELECT id, name, email, role, phone_number, is_verified FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        http_response_code(200);
        echo json_encode(array("user" => $user, "status" => true));
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "User not found", "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
}

mysqli_close($conn);
?>
