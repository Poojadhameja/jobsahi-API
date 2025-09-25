<?php
// get_employer_profiles.php - Get employer/recruiter profiles with admin_action filter
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
include "../db.php";

// ✅ Authenticate user and get decoded token
$decoded_token = authenticateJWT(['admin', 'recruiter']);
$user_role = $decoded_token['role']; // <-- fixed here

// Build SQL query based on role
if ($user_role === 'admin') {
    // Admin sees all pending and approved
    $sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
            FROM recruiter_profiles 
            WHERE deleted_at IS NULL
            AND (admin_action = 'pending' OR admin_action = 'approved')
            ORDER BY id DESC";
} else {
    // Other roles see only approved
    $sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
            FROM recruiter_profiles 
            WHERE deleted_at IS NULL
            AND admin_action = 'approved'
            ORDER BY id DESC";
}

$result = mysqli_query($conn, $sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
    exit;
}

if (mysqli_num_rows($result) > 0) {
    $profiles = mysqli_fetch_all($result, MYSQLI_ASSOC);
    http_response_code(200);
    echo json_encode(array("profiles" => $profiles, "count" => count($profiles), "status" => true));
} else {
    http_response_code(200);
    echo json_encode(array("profiles" => [], "count" => 0, "status" => true));
}

mysqli_close($conn);
?>
