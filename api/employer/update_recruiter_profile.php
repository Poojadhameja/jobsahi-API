<?php
include '../CORS.php';

// Authenticate user and get role
$current_user = authenticateJWT(['admin', 'recruiter']);
$user_role = $current_user['role'] ?? '';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON input", "status" => false));
    exit;
}

// Validate required fields
if (!isset($input['id']) || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Profile ID is required", "status" => false));
    exit;
}

$profile_id = intval($input['id']);

// Build update query dynamically based on provided fields
$update_fields = array();
$params = array();
$types = '';

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

// Build SQL query for update
$sql = "UPDATE recruiter_profiles SET " . implode(', ', $update_fields) . " WHERE id = ? AND deleted_at IS NULL";
$params[] = $profile_id;
$types .= 'i';

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

mysqli_stmt_close($stmt);

if ($affected_rows > 0) {
    // Build fetch query with role-based visibility
    if ($user_role === 'admin') {
        $fetch_sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
                      FROM recruiter_profiles 
                      WHERE id = ? AND deleted_at IS NULL";
        $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
        mysqli_stmt_bind_param($fetch_stmt, 'i', $profile_id);
    } else {
        // Non-admin users: only see admin_action = 'approval'
        $fetch_sql = "SELECT id, user_id, company_name, company_logo, industry, website, location, admin_action, created_at, modified_at 
                      FROM recruiter_profiles 
                      WHERE id = ? AND admin_action = 'approval' AND deleted_at IS NULL";
        $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
        mysqli_stmt_bind_param($fetch_stmt, 'i', $profile_id);
    }

    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);

    if ($updated_profile = mysqli_fetch_assoc($fetch_result)) {
        http_response_code(200);
        echo json_encode(array(
            "message" => "Profile updated successfully",
            "profile" => $updated_profile,
            "status" => true
        ));
    } else {
        http_response_code(403);
        echo json_encode(array(
            "message" => "Profile updated but you are not authorized to view it",
            "status" => false
        ));
    }

    mysqli_stmt_close($fetch_stmt);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "Profile not found or no changes made", "status" => false));
}

mysqli_close($conn);
?>
