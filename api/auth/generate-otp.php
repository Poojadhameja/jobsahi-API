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

// ✅ Validate purpose
$valid_purposes = ['signup', 'login', 'forgot_password', 'phone_verification'];
if (!in_array($purpose, $valid_purposes)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid purpose. Valid purposes: " . implode(', ', $valid_purposes), "status" => false]);
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

        // ✅ Generate OTP (4 digits for forgot_password, 6 for others)
        $otp_length = ($purpose === 'forgot_password') ? 4 : 6;
        $otp        = generateOTP($otp_length);
        $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 min expiry

        // ✅ Delete any existing OTP for this user and purpose first
        $delete_sql = "DELETE FROM otp_requests WHERE user_id = ? AND purpose = ?";
        if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
            mysqli_stmt_bind_param($delete_stmt, "is", $user_id, $purpose);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }

        // ✅ Insert new OTP
        $insert_sql = "INSERT INTO otp_requests (user_id, otp_code, purpose, is_used, created_at, expires_at) VALUES (?, ?, ?, 0, NOW(), ?)";
        
        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            mysqli_stmt_bind_param($insert_stmt, "isss", $user_id, $otp, $purpose, $expires_at);

            if (mysqli_stmt_execute($insert_stmt)) {
                // ✅ OTP saved to database successfully
                // ✅ Return response immediately (fast response)
                http_response_code(200);
                echo json_encode([
                    "message"    => "OTP generated successfully for $purpose",
                    "status"     => true,
                    "purpose"    => $purpose,
                    "user_id"    => $user_id,
                    "expires_in" => 300
                ]);
                
                // ✅ Send Email in background (non-blocking) after response is sent
                // Close output buffer and send email without blocking
                if (ob_get_level()) {
                    ob_end_flush();
                }
                flush();
                
                // Send email with timeout to prevent hanging
                set_time_limit(10); // Max 10 seconds for email
                try {
                    $email_sent = @sendPasswordResetOTP($email, $user['user_name'], $otp);
                    if (!$email_sent) {
                        error_log("Email sending failed for user_id: $user_id, purpose: $purpose, but OTP saved: $otp");
                    }
                } catch (Exception $e) {
                    error_log("Email sending error for user_id: $user_id: " . $e->getMessage());
                }
                
                // Exit to prevent any further execution
                exit;
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to save OTP", "status" => false]);
            }
            mysqli_stmt_close($insert_stmt);
        }
    } else {
        // Security: Don't reveal user existence for forgot_password
        if ($purpose === 'forgot_password') {
            echo json_encode([
                "message" => "If the email exists, a password reset OTP has been sent",
                "status"  => true
            ]);
        } else {
            echo json_encode([
                "message" => "No account found with this email address",
                "status"  => false
            ]);
        }
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database query failed", "status" => false]);
}

mysqli_close($conn);
?>
