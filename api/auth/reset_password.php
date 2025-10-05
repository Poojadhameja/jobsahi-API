<?php
// reset_password.php - Reset user password ONLY after OTP verification

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

// Validate password strength
if (strlen($new_password) < 6) {
    http_response_code(400);
    echo json_encode(["message" => "New password must be at least 6 characters long", "status" => false]);
    exit;
}

// ✅ Step 1: Check if user exists and get current password
$user_sql = "SELECT id, password FROM users WHERE id = ?";
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
    
    $user_row = mysqli_fetch_assoc($user_result);
    $current_password_hash = $user_row['password'];
    mysqli_stmt_close($user_stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// ✅ Step 1.5: Check if new password is different from current password
if (password_verify($new_password, $current_password_hash)) {
    http_response_code(400);
    echo json_encode([
        "message" => "New password must be different from your current password",
        "status" => false
    ]);
    mysqli_close($conn);
    exit;
}

// ✅ Step 2: Check if OTP was verified
$otp_check_sql = "SELECT id FROM otp_requests WHERE user_id = ? AND is_used = 1 ORDER BY created_at DESC LIMIT 1";
if ($otp_check_stmt = mysqli_prepare($conn, $otp_check_sql)) {
    mysqli_stmt_bind_param($otp_check_stmt, "i", $user_id);
    mysqli_stmt_execute($otp_check_stmt);
    $otp_check_result = mysqli_stmt_get_result($otp_check_stmt);

    if (mysqli_num_rows($otp_check_result) === 0) {
        mysqli_stmt_close($otp_check_stmt);
        http_response_code(403);
        echo json_encode([
            "message" => "OTP verification is pending. Please verify OTP first.",
            "status" => false
        ]);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($otp_check_stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// ✅ Step 3: Hash new password and update
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
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
