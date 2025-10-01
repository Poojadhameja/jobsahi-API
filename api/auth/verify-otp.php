<?php
<<<<<<< HEAD
<<<<<<< HEAD
// verify-otp.php - Verify OTP (for any purpose)
require_once '../cors.php';
=======
require '../cors.php';
>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
require '../cors.php';
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON data", "status" => false]);
    exit;
}

// Validate required fields
if (!isset($data['user_id']) || !isset($data['otp'])) {
    http_response_code(400);
    echo json_encode([
        "message" => "User ID and OTP are required", 
        "status" => false
    ]);
    exit;
}

$user_id = intval($data['user_id']);
$otp = trim($data['otp']);

// Validate inputs
if ($user_id <= 0 || empty($otp)) {
    http_response_code(400);
    echo json_encode([
        "message" => "Valid User ID and OTP are required", 
        "status" => false
    ]);
    exit;
}

// ✅ Step 1: Check if user exists
$user_sql = "SELECT id FROM users WHERE id = ?";
if ($user_stmt = mysqli_prepare($conn, $user_sql)) {
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);

    if (mysqli_num_rows($user_result) === 0) {
        mysqli_stmt_close($user_stmt);
        http_response_code(400);
        echo json_encode([
            "message" => "Invalid user_id. Please enter correct user_id",
            "status" => false
        ]);
        exit;
    }
    mysqli_stmt_close($user_stmt);
}

// ✅ Step 2: Verify OTP for that user
$otp_sql = "SELECT id, otp_code, expires_at, is_used 
            FROM otp_requests 
            WHERE user_id = ? 
            AND is_used = 0 
            ORDER BY created_at DESC 
            LIMIT 1";

if ($otp_stmt = mysqli_prepare($conn, $otp_sql)) {
    mysqli_stmt_bind_param($otp_stmt, "i", $user_id);
    mysqli_stmt_execute($otp_stmt);
    $otp_result = mysqli_stmt_get_result($otp_stmt);
    
    if (mysqli_num_rows($otp_result) > 0) {
        $otp_record = mysqli_fetch_assoc($otp_result);
        
        // Check expiry
        $current_time = date('Y-m-d H:i:s');
        if ($current_time > $otp_record['expires_at']) {
            mysqli_stmt_close($otp_stmt);
            http_response_code(400);
            echo json_encode([
                "message" => "User id is invalid. Pls enter correct user_id", 
                "status" => false,
                "expired" => true
            ]);
            exit;
        }
        
        // Match OTP
        if ($otp_record['otp_code'] === $otp) {
            // Mark OTP used
            $update_sql = "UPDATE otp_requests SET is_used = 1 WHERE id = ?";
            if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "i", $otp_record['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            
            mysqli_stmt_close($otp_stmt);
            http_response_code(200);
            echo json_encode([
                "message" => "OTP verified successfully",
                "status" => true,
                "user_id" => $user_id
            ]);
        } else {
            mysqli_stmt_close($otp_stmt);
            http_response_code(400);
            echo json_encode([
<<<<<<< HEAD
<<<<<<< HEAD
                "message" => "Invalid OTP. Please check and try again", 
=======
                "message" => "Invalid OTP or Purpose. Please check and try again", 
>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
                "message" => "Invalid OTP or Purpose. Please check and try again", 
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
                "status" => false
            ]);
        }
    } else {
        mysqli_stmt_close($otp_stmt);
        http_response_code(400);
        echo json_encode([
            "message" => "No valid OTP found. Please request a new one", 
            "status" => false
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode([
        "message" => "Database error", 
        "status" => false
    ]);
}

mysqli_close($conn);
?>
