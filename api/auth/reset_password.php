<?php
// reset_password.php - Reset user password without token (after OTP verification)

require_once '../cors.php';

header('Content-Type: application/json');

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON data", "status" => false]);
    exit;
}

// Validate required fields
if (!isset($data['user_id']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(["message" => "User ID and new password are required", "status" => false]);
    exit;
}

$user_id = intval($data['user_id']);
$new_password = trim($data['new_password']);

if (empty($user_id) || empty($new_password)) {
    http_response_code(400);
    echo json_encode(["message" => "User ID and new password cannot be empty", "status" => false]);
    exit;
}

// Validate new password strength (example: at least 6 chars)
if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(["message" => "New password must be at least 6 characters long", "status" => false]);
    exit;
}

// Check if user exists
$user_sql = "SELECT id FROM users WHERE id = ?";
if ($user_stmt = mysqli_prepare($conn, $user_sql)) {
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);

    if (mysqli_num_rows($user_result) === 0) {
        http_response_code(404);
        echo json_encode(["message" => "User not found", "status" => false]);
        mysqli_stmt_close($user_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($user_stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . mysqli_error($conn), "status" => false]);
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
        echo json_encode(["message" => "Password reset successfully", "status" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to reset password", "status" => false]);
    }

    mysqli_stmt_close($update_stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . mysqli_error($conn), "status" => false]);
}

mysqli_close($conn);
?>
