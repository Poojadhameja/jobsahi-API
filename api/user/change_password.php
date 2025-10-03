<?php
// change_password.php - Change user password
require_once '../cors.php';

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['uid'];
$current_password = $data['current_password'];
$new_password = $data['new_password'];

$check_sql = "SELECT password FROM users WHERE id = {$user_id}";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(array("message" => "User not found.", "status" => false));
    exit;
}

$user = mysqli_fetch_assoc($check_result);

// In production, use password_verify() if passwords are hashed
if ($user['password'] !== $current_password) {
    echo json_encode(array("message" => "Current password is incorrect.", "status" => false));
    exit;
}

// Update password (consider using password_hash() in production)
$sql = "UPDATE users SET password = '{$new_password}' WHERE id = {$user_id}";

if (mysqli_query($conn, $sql)) {
    echo json_encode(array("message" => "Password changed successfully.", "status" => true));
} else {
    echo json_encode(array("message" => "Password not changed.", "status" => false));
}
?>
