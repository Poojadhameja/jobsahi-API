<?php
require '../cors.php';
require '../helpers/email_helper.php';
require '../helpers/otp_helper.php';

// ✅ Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed", "status" => false]);
    exit;
}

// ✅ Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON data", "status" => false]);
    exit;
}

// ✅ Validate required fields
if (!isset($data['email']) || !isset($data['purpose'])) {
    http_response_code(400);
    echo json_encode(["message" => "Email and purpose are required", "status" => false]);
    exit;
}

$email   = trim($data['email']);
$purpose = trim($data['purpose']);

if (empty($email) || empty($purpose)) {
    http_response_code(400);
    echo json_encode(["message" => "Email and purpose cannot be empty", "status" => false]);
    exit;
}

// ✅ Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid email format", "status" => false]);
    exit;
}

// ✅ Check if user exists
$sql = "SELECT id, user_name, email FROM users WHERE email = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user    = mysqli_fetch_assoc($result);
        $user_id = $user['id'];

        // ✅ Generate OTP
        $otp        = generateOTP();
        $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 min expiry

        // ✅ Insert or Update OTP (UPSERT)
        $insert_sql = "
           INSERT INTO otp_requests (user_id, otp_code, purpose, is_used, created_at, expires_at) 
VALUES (?, ?, ?, 0, NOW(), ?)
ON DUPLICATE KEY UPDATE 
    otp_code = VALUES(otp_code),
    purpose = VALUES(purpose),
    is_used = 0,
    created_at = NOW(),
    expires_at = VALUES(expires_at)

        ";

        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            mysqli_stmt_bind_param($insert_stmt, "isss", $user_id, $otp, $purpose, $expires_at);

            if (mysqli_stmt_execute($insert_stmt)) {
                // ✅ Send Email
                $email_sent = sendPasswordResetOTP($email, $user['user_name'], $otp);

                if ($email_sent) {
                    echo json_encode([
                        "message"    => "OTP sent successfully for $purpose",
                        "status"     => true,
                        "purpose"    => $purpose,
                        "user_id"    => $user_id,
                        "expires_in" => 300
                    ]);
                } else {
                    echo json_encode(["message" => "Failed to send OTP email", "status" => false]);
                }
            } else {
                echo json_encode(["message" => "Failed to save OTP", "status" => false]);
            }
            mysqli_stmt_close($insert_stmt);
        }
    } else {
        // Security: Don’t reveal user existence
        echo json_encode([
            "message" => "If the email exists, a password reset OTP has been sent",
            "status"  => true
        ]);
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database query failed", "status" => false]);
}

mysqli_close($conn);