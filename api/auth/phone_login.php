<?php
// phone-login.php - Send OTP for phone login
require_once '../cors.php';
require_once '../db.php'; // ✅ make sure this has $conn
require_once '../helpers/otp_helper.php';

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
if (!isset($data['phone_number'])) {
    http_response_code(400);
    echo json_encode(["message" => "Phone number is required", "status" => false]);
    exit;
}

$phone = trim($data['phone_number']);

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(["message" => "Phone number cannot be empty", "status" => false]);
    exit;
}

// ✅ Validate phone format (basic validation - adjust as needed)
if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid phone number format", "status" => false]);
    exit;
}

// ✅ Check if user exists with this phone number
$sql = "SELECT id, user_name, phone_number FROM users WHERE phone_number = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $phone);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $user_id = $user['id'];

        // ✅ Generate OTP
        $otp = generateOTP();
        $purpose = 'phone_login';
        $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiry

        // ✅ Delete any existing OTP requests for this user and purpose
        $delete_sql = "DELETE FROM otp_requests WHERE user_id = ? AND purpose = ?";
        if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
            mysqli_stmt_bind_param($delete_stmt, "is", $user_id, $purpose);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);
        }

        // ✅ Insert new OTP request
        $insert_sql = "INSERT INTO otp_requests (user_id, otp_code, purpose, is_used, created_at, expires_at) 
                       VALUES (?, ?, ?, 0, NOW(), ?)";
        if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
            mysqli_stmt_bind_param($insert_stmt, "isss", $user_id, $otp, $purpose, $expires_at);

            if (mysqli_stmt_execute($insert_stmt)) {
                // ✅ Send SMS with OTP (implement later)
                http_response_code(200);
                echo json_encode([
                    "message"    => "OTP sent to your phone",
                    "status"     => true,
                    "expires_in" => 300
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to generate OTP", "status" => false]);
            }
            mysqli_stmt_close($insert_stmt);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Database error (insert prepare failed)", "status" => false]);
        }
    } else {
        // ✅ Security: don't reveal if phone exists
        http_response_code(200);
        echo json_encode([
            "message" => "The phone number don't exists, an OTP not has been sent",
            "status"  => false
        ]);
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database query failed", "status" => false]);
}

mysqli_close($conn);
?>
