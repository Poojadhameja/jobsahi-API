<?php
<<<<<<< HEAD
<<<<<<< HEAD
// resend-otp.php - Fixed version
error_reporting(E_ALL);
ini_set('display_errors', 1);
=======
require '../cors.php';
require_once '../helpers/email_helper.php';
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1

function send_response($success, $message, $data = [], $code = 200) {
    http_response_code($success ? 200 : $code);
    echo json_encode([
<<<<<<< HEAD
        "success" => $success,
        "message" => $message,
        "data" => $data,
=======
require '../cors.php';
require_once '../helpers/email_helper.php';

function send_response($success, $message, $data = [], $code = 200)
{
    http_response_code($success ? 200 : $code);
    echo json_encode([
        "success"   => $success,
        "message"   => $message,
        "data"      => $data,
>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
        "success"   => $success,
        "message"   => $message,
        "data"      => $data,
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    exit;
}

<<<<<<< HEAD
<<<<<<< HEAD
// Check if config file exists
if (!file_exists('../db.php')) {
    send_response(false, "Configuration error", [], 500);
}

require_once '../db.php';

// Check database connection
if (!isset($conn) || !$conn) {
    send_response(false, "Database connection failed", [], 500);
}

// Check if email helper exists
if (!file_exists('../helpers/email_helper.php')) {
    send_response(false, "Email service unavailable", [], 500);
}

require_once '../helpers/email_helper.php';

// Check if the function exists
if (!function_exists('sendPasswordResetOTP')) {
    send_response(false, "Email service not configured", [], 500);
}

// Check request method
=======
>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, "Method not allowed", [], 405);
}

<<<<<<< HEAD
<<<<<<< HEAD
// Get and validate input
$json_input = file_get_contents("php://input");

if (empty($json_input)) {
    send_response(false, "No data received", [], 400);
}

$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_response(false, "Invalid JSON format", [], 400);
}

if (!isset($data['email']) || empty($data['email'])) {
=======
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['email']) || empty(trim($data['email']))) {
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
    send_response(false, "Email is required", [], 400);
}
if (!isset($data['purpose']) || empty(trim($data['purpose']))) {
    send_response(false, "Purpose is required", [], 400);
}

$email   = trim($data['email']);
$purpose = trim($data['purpose']);

<<<<<<< HEAD
$email = trim($data['email']);
=======
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['email']) || empty(trim($data['email']))) {
    send_response(false, "Email is required", [], 400);
}
if (!isset($data['purpose']) || empty(trim($data['purpose']))) {
    send_response(false, "Purpose is required", [], 400);
}

$email   = trim($data['email']);
$purpose = trim($data['purpose']);

>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_response(false, "Invalid email format", [], 400);
}

<<<<<<< HEAD
<<<<<<< HEAD
// First, let's check the structure of otp_requests table
$table_structure = mysqli_query($conn, "DESCRIBE otp_requests");
if (!$table_structure) {
    send_response(false, "Cannot access OTP table", [], 500);
}

// Get table columns
$columns = [];
while ($row = mysqli_fetch_assoc($table_structure)) {
    $columns[] = $row['Field'];
}

// Check if required columns exist
$required_columns = ['user_id', 'otp_code', 'expires_at'];
$has_email_column = in_array('email', $columns);
$has_user_id_column = in_array('user_id', $columns);

if (!$has_email_column && !$has_user_id_column) {
    send_response(false, "OTP table structure is invalid", ["available_columns" => $columns], 500);
}

// Check if user exists
$sql = "SELECT id, user_name, email FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    send_response(false, "Database error", [], 500);
}

=======
// ✅ User check
$sql = "SELECT id, user_name FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
// ✅ User check
$sql = "SELECT id, user_name FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

<<<<<<< HEAD
<<<<<<< HEAD
if (mysqli_num_rows($result) == 0) {
=======
if (mysqli_num_rows($result) === 0) {
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
    send_response(false, "No account found with this email address", [], 404);
}
$user    = mysqli_fetch_assoc($result);
$user_id = $user['id'];
mysqli_stmt_close($stmt);

// ✅ Generate OTP
$otp        = sprintf("%06d", mt_rand(100000, 999999));
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
        send_response(true, "New OTP sent successfully", [
            "email"      => $email,
            "purpose"    => $purpose,
            "otp"        => $otp,   // debug ke liye show kar raha hoon (live me hata dena)
            "expires_in" => "15 minutes"
        ]);
    } else {
        send_response(false, "Failed to send OTP email", [], 500);
    }
} else {
    send_response(false, "Failed to save OTP in database", [], 500);
}

mysqli_close($conn);
?>
<<<<<<< HEAD
=======
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
>>>>>>> dfdb9388f97f0ad9898e04e43042129728ce7246
=======
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1
