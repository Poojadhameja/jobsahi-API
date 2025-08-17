<?php
// delete_user.php - Delete user (Admin only or own account)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once 'jwt_helper.php';
require_once 'auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(array("message" => "Only DELETE requests allowed", "status" => false));
    exit;
}

// Authenticate user
$current_user = authenticateJWT();

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['uid']) || empty($data['uid'])) {
    http_response_code(400);
    echo json_encode(array("message" => "User ID is required", "status" => false));
    exit;
}

$user_id = intval($data['uid']);

// Check if user can delete this account (admin or own account)
if ($current_user['role'] !== 'admin' && $current_user['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(array("message" => "Access denied", "status" => false));
    exit;
}

include "config.php";

// Check if user exists
$check_sql = "SELECT id FROM users WHERE id = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
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

// Delete user
$sql = "DELETE FROM users WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        http_response_code(200);
        echo json_encode(array("message" => "User deleted successfully", "status" => true));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Failed to delete user", "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed", "status" => false));
}

mysqli_close($conn);
?>