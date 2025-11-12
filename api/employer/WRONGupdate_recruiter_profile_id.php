<?php
// update_recruiter_profile_id.php - Update recruiter/company profile by id
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow PUT method
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(array("message" => "Method not allowed", "status" => false));
    exit;
}

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
include "../db.php";

// Authenticate user and get role
$current_user = authenticateJWT(['admin', 'recruiter', 'institute', 'student']);
$user_role = $current_user['role'] ?? '';

// Extract ID from query parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Profile ID is required in query parameter", "status" => false));
    exit;
}

$profile_id = intval($_GET['id']);
if ($profile_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid profile ID", "status" => false));
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if ($input === null) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON input", "status" => false));
    exit;
}

// Build update query dynamically based on provided fields
$update_fields = [];
$params = [];
$types = '';

// Define allowed fields for update
$allowed_fields = ['company_name', 'company_logo', 'industry', 'website', 'location', 'admin_action'];

foreach ($allowed_fields as $field) {
    if (isset($input[$field])) {
        $update_fields[] = "$field = ?";
        $params[] = $input[$field];
        $types .= 's';
    }
}

if (empty($update_fields)) {
    http_response_code(400);
    echo json_encode(array("message" => "No valid fields provided for update", "status" => false));
    exit;
}

// Add modified_at timestamp
$update_fields[] = "modified_at = NOW()";

// Build the SQL query
$sql = "UPDATE recruiter_profiles SET " . implode(', ', $update_fields) . " WHERE id = ? AND deleted_at IS NULL";
$params[] = $profile_id;
$types .= 'i';

// Prepare and execute the statement
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed", "status" => false));
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
$result = mysqli_stmt_execute($stmt);

if (!$result) {
    http_response_code(500);
    echo json_encode(array("message" => "Database update failed", "status" => false));
    exit;
}

$affected_rows = mysqli_stmt_affected_rows($stmt);

if ($affected_rows > 0) {
    // Fetch updated profile with role-based visibility
    $fetch_sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
                  FROM recruiter_profiles 
                  WHERE id = ? AND deleted_at IS NULL";

    // Role-based filtering: if user is not admin, only fetch approved records
    if ($user_role !== 'admin') {
        $fetch_sql .= " AND admin_action = 'approved'";
    }

    $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
    mysqli_stmt_bind_param($fetch_stmt, 'i', $profile_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);

    if ($updated_profile = mysqli_fetch_assoc($fetch_result)) {
        http_response_code(200);
        echo json_encode(array(
            "message" => "Employer profile updated successfully",
            "data" => $updated_profile,
            "status" => true
        ));
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Profile updated but not visible to your role", "status" => false));
    }

    mysqli_stmt_close($fetch_stmt);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "Employer profile not found or no changes made", "status" => false));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
