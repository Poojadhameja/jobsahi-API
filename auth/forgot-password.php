<?php
// forgot-password.php - Send password reset OTP/email
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../db.php';
require_once '../helpers/email_helper.php';
require_once '../helpers/otp_helper.php'; // use helper only, no duplicate generateOTP

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON data", "status" => false]);
    exit;
}

// Validate required fields
if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(["message" => "Email is required", "status" => false]);
    exit;
}

$email = trim($data['email']);

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["message" => "Email cannot be empty", "status" => false]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid email format", "status" => false]);
    exit;
}

// Check if user exists
$sql = "SELECT id, name, email FROM users WHERE email = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $user_id = $user['id'];
        
        // Generate OTP using helper
        $otp = generateOTP(); // from otp_helper.php
        $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiry
        $purpose = 'password_reset';
        
        // Delete any existing OTP requests for this user and purpose
        $delete_sql = "DELETE FROM otp_requests WHERE user_id = ? AND purpose = ?";
        if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
            mysqli_stmt_bind_param($delete_stmt, "is", $user_id, $purpose);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }
        
        // Insert new OTP request
        $insert_sql = "INSERT INTO otp_requests (user_id, otp_code, purpose, is_used, created_at, expires_at) 
                       VALUES (?, ?, ?, 0, NOW(), ?)";
        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            mysqli_stmt_bind_param($insert_stmt, "isss", $user_id, $otp, $purpose, $expires_at);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                // Send email with OTP
                $email_sent = sendPasswordResetOTP($email, $user['name'], $otp);
                
                if ($email_sent) {
                    http_response_code(200);
                    echo json_encode([
                        "message" => "Password reset OTP sent to your email",
                        "status" => true,
                        "expires_in" => 300
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to send OTP email", "status" => false]);
                }
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to generate OTP", "status" => false]);
            }
            mysqli_stmt_close($insert_stmt);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Database error", "status" => false]);
        }
    } else {
        // Security: don’t reveal if user exists
        http_response_code(200);
        echo json_encode([
            "message" => "If the email exists, a password reset OTP has been sent",
            "status" => true
        ]);
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database query failed", "status" => false]);
}

mysqli_close($conn);
?>
