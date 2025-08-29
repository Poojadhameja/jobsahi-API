<?php
// get_all_users.php - Get all users (Admin only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate and check for admin role
authenticateJWT('admin');

include "../database/db.php";

$sql = "SELECT id, name, email, role, phone_number, is_verified FROM users ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
    exit;
}

if (mysqli_num_rows($result) > 0) {
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
    http_response_code(200);
    echo json_encode(array("users" => $users, "count" => count($users), "status" => true));
} else {
    http_response_code(200);
    echo json_encode(array("users" => [], "count" => 0, "status" => true));
}

mysqli_close($conn);
?>
