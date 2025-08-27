<?php
// verify_user.php - Verify/Unverify user
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['uid'];
$is_verified = $data['is_verified']; // 1 for verified, 0 for unverified

include "../config.php";

// Check if user exists
$check_sql = "SELECT id FROM users WHERE id = {$user_id}";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(array("message" => "User not found.", "status" => false));
    exit;
}

$sql = "UPDATE users SET is_verified = {$is_verified} WHERE id = {$user_id}";

if (mysqli_query($conn, $sql)) {
    $status_text = $is_verified ? "verified" : "unverified";
    echo json_encode(array("message" => "User {$status_text} successfully.", "status" => true));
} else {
    echo json_encode(array("message" => "User verification status not updated.", "status" => false));
}
?>
