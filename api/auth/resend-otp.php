<?php
require '../cors.php';
require_once '../helpers/email_helper.php';

function send_response($success, $message, $data = [], $code = 200)
{
    http_response_code($success ? 200 : $code);
    echo json_encode([
        "success"   => $success,
        "message"   => $message,
        "data"      => $data,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, "Method not allowed", [], 405);
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['email']) || empty(trim($data['email']))) {
    send_response(false, "Email is required", [], 400);
}
if (!isset($data['purpose']) || empty(trim($data['purpose']))) {
    send_response(false, "Purpose is required", [], 400);
}

$email   = trim($data['email']);
$purpose = trim($data['purpose']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_response(false, "Invalid email format", [], 400);
}

// ✅ User check
$sql = "SELECT id, user_name FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    send_response(false, "No account found with this email address", [], 404);
}
$user    = mysqli_fetch_assoc($result);
$user_id = $user['id'];
mysqli_stmt_close($stmt);

// ✅ Generate OTP
$otp        = sprintf("%04d", mt_rand(1000, 9999));
$expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));


// ✅ Insert or Update with purpose
$query = "
INSERT INTO otp_requests (user_id, otp_code, purpose, is_used, created_at, expires_at) 
VALUES (?, ?, ?, 0, NOW(), ?)
ON DUPLICATE KEY UPDATE 
    otp_code   = VALUES(otp_code),
    purpose    = VALUES(purpose),
    is_used    = 0,
    created_at = NOW(),
    expires_at = VALUES(expires_at)
";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    send_response(false, "Database error: " . mysqli_error($conn), [], 500);
}
mysqli_stmt_bind_param($stmt, "isss", $user_id, $otp, $purpose, $expires_at);
$success = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// ✅ Send email
if ($success) {
    if (sendPasswordResetOTP($email, $user['user_name'], $otp)) {
        // send_response(true, "New OTP sent successfully", [
        //     "email"      => $email,
        //     "purpose"    => $purpose,
        //     "otp"        => $otp,   // debug ke liye show kar raha hoon (live me hata dena)
        //     "user_id"    => $user_id,
        //     "expires_in" => "15 minutes"
        // ]);
        echo json_encode([
            "message"    => "New OTP sent successfully",
            "status"     => true,
            "purpose"    => $purpose,
            "otp"        => $otp,   // debug ke liye show kar raha hoon (live me hata dena)
            "user_id"    => $user_id,
            "expires_in" => 300
        ]);
    } else {
        // send_response(false, "Failed to send OTP email", [], 500);
        echo json_encode([
            "message"    => "Failed to send OTP email",
            "status"     => false,
        ]);
    }
} else {
    // send_response(false, "Failed to save OTP in database", [], 500);
    echo json_encode(["message" => "Failed to save OTP in database", "status" => false]);
}

mysqli_close($conn);
