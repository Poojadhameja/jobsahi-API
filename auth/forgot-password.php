<?php
// forgot-password.php - Send password reset OTP/email
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../database/config.php';
require_once '../helpers/email_helper.php'; // Assuming you have email helper
// Removed: require_once '../helpers/otp_helper.php'; - Not needed since generateOTP is defined below

// Helper function to generate OTP (moved to top for better organization)
function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("message" => "Only POST requests allowed", "status" => false));
    exit;
}

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

// Validate required fields
if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Email is required", "status" => false));
    exit;
}

$email = trim($data['email']);

if (empty($email)) {
    http_response_code(400);
    echo json_encode(array("message" => "Email cannot be empty", "status" => false));
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid email format", "status" => false));
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
        
        // Generate OTP
        $otp = generateOTP(); // 6-digit OTP
        $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiry
        $otp_type = 'password_reset';
        
        // Delete any existing OTP requests for this email and type
        $delete_sql = "DELETE FROM otp_requests WHERE email = ? AND type = ?";
        if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
            mysqli_stmt_bind_param($delete_stmt, "ss", $email, $otp_type);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }
        
        // Insert new OTP request
        $insert_sql = "INSERT INTO otp_requests (email, otp, type, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())";
        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            mysqli_stmt_bind_param($insert_stmt, "ssss", $email, $otp, $otp_type, $expires_at);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                // Send email with OTP
                $email_sent = sendPasswordResetOTP($email, $user['name'], $otp);
                
                if ($email_sent) {
                    http_response_code(200);
                    echo json_encode(array(
                        "message" => "Password reset OTP sent to your email",
                        "status" => true,
                        "expires_in" => 300 // 5 minutes
                    ));
                } else {
                    http_response_code(500);
                    echo json_encode(array("message" => "Failed to send OTP email", "status" => false));
                }
            } else {
                http_response_code(500);
                echo json_encode(array("message" => "Failed to generate OTP", "status" => false));
            }
            mysqli_stmt_close($insert_stmt);
        } else {
            http_response_code(500);
            echo json_encode(array("message" => "Database error", "status" => false));
        }
    } else {
        // For security, don't reveal if email exists or not
        http_response_code(200);
        echo json_encode(array(
            "message" => "If the email exists, a password reset OTP has been sent",
            "status" => true
        ));
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
}

mysqli_close($conn);
?>