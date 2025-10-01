<?php
// get_users_by_role.php - Get users by role
require_once '../cors.php';

$data = json_decode(file_get_contents('php://input'), true);
$role = isset($data['role']) ? $data['role'] : '';

if (empty($role)) {
    echo json_encode(array("message" => "Role is required", "status" => false));
    exit;
}

// Validate role
$valid_roles = ['student', 'recruiter', 'institute', 'admin'];
if (!in_array($role, $valid_roles)) {
    echo json_encode(array("message" => "Invalid role", "status" => false));
    exit;
}

include "../db.php";

$sql = "SELECT id, user_name, email, role, phone_number, is_verified 
        FROM users 
        WHERE role = '{$role}' 
        ORDER BY user_name ASC";
$result = mysqli_query($conn, $sql) or die("SQL Query Failed.");

if(mysqli_num_rows($result) > 0){
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode(array("users" => $users, "count" => count($users), "status" => true));
} else {
    echo json_encode(array("message" => "No users found for this role", "users" => [], "status" => true));
}
?>