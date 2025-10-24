<?php
require_once '../cors.php';

try {
    // ✅ Authenticate JWT (allowed roles: admin, institute)
    $current_user = authenticateJWT(['admin', 'institute']); 
    $user_role = strtolower($current_user['role']);
    $user_id = $current_user['user_id'] ?? null;
} catch (Exception $e) {
    echo json_encode(["message" => "Authentication failed: " . $e->getMessage(), "status" => false]);
    exit;
}

// ✅ Allow only PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["message" => "Only PUT requests allowed", "status" => false]);
    exit;
}

// ✅ Get institute profile ID from URL
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($request_uri, '/'));
$profile_id = end($path_parts);

if (!$profile_id || !is_numeric($profile_id)) {
    echo json_encode(["message" => "Invalid profile ID", "status" => false]);
    exit;
}

// ✅ Get JSON input data
$json_input = file_get_contents('php://input');
$input_data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["message" => "Invalid JSON data", "status" => false]);
    exit;
}


if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ✅ Check if profile exists and get current data
$check_sql = "SELECT id, user_id, admin_action FROM institute_profiles WHERE id = ? AND deleted_at IS NULL";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $profile_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) == 0) {
    echo json_encode(["message" => "Institute profile not found", "status" => false]);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit;
}

$existing_profile = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

// ✅ Permission check
if ($user_role === 'institute' && $existing_profile['user_id'] != $user_id) {
    echo json_encode(["message" => "Access denied: You can only update your own profile", "status" => false]);
    mysqli_close($conn);
    exit;
}

// ✅ Prepare update fields
$allowed_fields = ['location', 'courses_offered'];
$update_fields = [];
$update_values = [];
$param_types = '';

foreach ($allowed_fields as $field) {
    if (isset($input_data[$field])) {
        $update_fields[] = "$field = ?";
        $update_values[] = $input_data[$field];
        $param_types .= 's';
    }
}

// ✅ Admin can update admin_action
if ($user_role === 'admin' && isset($input_data['admin_action'])) {
    $valid_actions = ['pending', 'approved', 'rejected'];
    if (in_array($input_data['admin_action'], $valid_actions)) {
        $update_fields[] = "admin_action = ?";
        $update_values[] = $input_data['admin_action'];
        $param_types .= 's';
    }
}

if (empty($update_fields)) {
    echo json_encode(["message" => "No valid fields to update", "status" => false]);
    mysqli_close($conn);
    exit;
}

// ✅ Add modified_at timestamp
$update_fields[] = "modified_at = NOW()";

// ✅ Build and execute update query
$sql = "UPDATE institute_profiles SET " . implode(', ', $update_fields) . " WHERE id = ?";
$update_values[] = $profile_id;
$param_types .= 'i';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $param_types, ...$update_values);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        // ✅ Fetch updated profile
        $fetch_sql = "SELECT 
                        id, 
                        user_id, 
                        location, 
                        courses_offered, 
                        admin_action,
                        created_at, 
                        modified_at, 
                        deleted_at
                      FROM institute_profiles 
                      WHERE id = ? AND deleted_at IS NULL";
        
        $fetch_stmt = mysqli_prepare($conn, $fetch_sql);
        mysqli_stmt_bind_param($fetch_stmt, "i", $profile_id);
        mysqli_stmt_execute($fetch_stmt);
        $fetch_result = mysqli_stmt_get_result($fetch_stmt);
        $updated_profile = mysqli_fetch_assoc($fetch_result);
        mysqli_stmt_close($fetch_stmt);

        echo json_encode([
            "message" => "Institute profile updated successfully",
            "status" => true,
            "data" => $updated_profile,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            "message" => "No changes were made to the profile",
            "status" => true,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    }
} else {
    echo json_encode([
        "message" => "Failed to update institute profile: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>