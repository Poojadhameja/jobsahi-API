<?php
// verify-otp.php - Debug version
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../config.php';

// Function to debug response
function debug_response($message, $data = [], $status = false, $code = 400) {
    http_response_code($code);
    echo json_encode([
        "message" => $message,
        "status" => $status,
        "debug_data" => $data,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_response("Only POST requests allowed", [], false, 405);
}

// Read JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    debug_response("Invalid JSON data", ["json_error" => json_last_error_msg()], false, 400);
}

// Validate fields
if (!isset($data['user_id'], $data['otp'], $data['purpose'])) {
    debug_response("Missing parameters", [
        "has_user_id" => isset($data['user_id']),
        "has_otp" => isset($data['otp']),
        "has_purpose" => isset($data['purpose']),
        "received_keys" => array_keys($data)
    ]);
}

$user_id = intval($data['user_id']);
$otp = trim($data['otp']);
$purpose = trim($data['purpose']);

if (empty($otp) || empty($purpose)) {
    debug_response("OTP and purpose validation failed", [
        "otp_empty" => empty($otp),
        "purpose_empty" => empty($purpose),
        "otp_value" => $otp,
        "purpose_value" => $purpose
    ]);
}

// Test database connection
if (!$conn) {
    debug_response("Database connection failed", ["error" => mysqli_connect_error()]);
}

// Check if table exists
$table_check = mysqli_query($conn, "DESCRIBE otp_requests");
if (!$table_check) {
    debug_response("Table otp_requests does not exist", ["error" => mysqli_error($conn)]);
}

// Get table structure
$columns = [];
while ($column = mysqli_fetch_assoc($table_check)) {
    $columns[] = $column;
}

// Check what's in the database for this user
$debug_sql = "SELECT * FROM otp_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
if ($debug_stmt = mysqli_prepare($conn, $debug_sql)) {
    mysqli_stmt_bind_param($debug_stmt, "i", $user_id);
    mysqli_stmt_execute($debug_stmt);
    $debug_result = mysqli_stmt_get_result($debug_stmt);
    
    $all_otps = [];
    while ($debug_row = mysqli_fetch_assoc($debug_result)) {
        $all_otps[] = $debug_row;
    }
    mysqli_stmt_close($debug_stmt);
    
    // THIS will now run and return status: true
    debug_response("Database query successful", [
        // "table_structure" => $columns,
        "search_params" => [
            "user_id" => $user_id,
            "otp" => $otp,
            "purpose" => $purpose
        ],
        
        // "found_otps" => $all_otps,
        // "total_otps_found" => count($all_otps)
    ], true, 200);
} else {
    debug_response("Database prepare failed", ["error" => mysqli_error($conn)]);
}

mysqli_close($conn);
?>