<?php
// bulk_operations.php - Bulk operations for users
require_once '../cors.php';

$data = json_decode(file_get_contents('php://input'), true);
$operation = $data['operation']; // 'verify', 'unverify', 'delete'
$user_ids = $data['user_ids']; // Array of user IDs

if (empty($user_ids) || !is_array($user_ids)) {
    echo json_encode(array("message" => "User IDs array is required", "status" => false));
    exit;
}

$user_ids_str = implode(',', array_map('intval', $user_ids));
$success_count = 0;

switch ($operation) {
    case 'verify':
        $sql = "UPDATE users SET is_verified = 1 WHERE id IN ({$user_ids_str})";
        break;
    case 'unverify':
        $sql = "UPDATE users SET is_verified = 0 WHERE id IN ({$user_ids_str})";
        break;
    case 'delete':
        $sql = "DELETE FROM users WHERE id IN ({$user_ids_str})";
        break;
    default:
        echo json_encode(array("message" => "Invalid operation", "status" => false));
        exit;
}

if (mysqli_query($conn, $sql)) {
    $affected_rows = mysqli_affected_rows($conn);
    echo json_encode(array(
        "message" => "Bulk operation completed successfully", 
        "affected_rows" => $affected_rows,
        "status" => true
    ));
} else {
    echo json_encode(array("message" => "Bulk operation failed", "status" => false));
}
?>