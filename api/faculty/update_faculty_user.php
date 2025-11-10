<?php
// update_faculty_user.php - Update an existing faculty user
require_once '../cors.php';

// ✅ Authenticate and allow only "admin" and "institute"
$decoded = authenticateJWT(['admin','institute']); 

// ✅ Extract user_id and role from token
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// ✅ Determine institute_id based on user role
$user_institute_id = null;

if ($user_role === 'admin') {
    // Admin can work with any institute
    // Fetch their institute_id from users table (optional)
    $fetch_institute_sql = "SELECT institute_id FROM users WHERE id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);
    
    if ($row = mysqli_fetch_assoc($fetch_result)) {
        $user_institute_id = $row['institute_id'];
    }
    mysqli_stmt_close($fetch_stmt);
} elseif ($user_role === 'institute') {
    // For institute role, fetch institute_id from institute_profiles table
    $fetch_institute_sql = "SELECT id FROM institute_profiles WHERE user_id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);
    
    if ($row = mysqli_fetch_assoc($fetch_result)) {
        $user_institute_id = $row['id'];
    }
    mysqli_stmt_close($fetch_stmt);
    
    // Institute must have an institute_id
    if ($user_institute_id === null) {
        echo json_encode([
            "message" => "Institute ID not found for the user",
            "status" => false
        ]);
        exit;
    }
}

// ✅ Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// ✅ Validate required field (faculty_id)
if (empty($data['id'])) {
    echo json_encode([
        "message" => "Faculty ID is required",
        "status" => false
    ]);
    exit;
}

$faculty_id = intval($data['id']);

// ✅ Check if faculty user exists and get their institute_id
$check_faculty_sql = "SELECT institute_id FROM faculty_users WHERE id = ?";
$check_faculty_stmt = mysqli_prepare($conn, $check_faculty_sql);
mysqli_stmt_bind_param($check_faculty_stmt, "i", $faculty_id);
mysqli_stmt_execute($check_faculty_stmt);
$faculty_result = mysqli_stmt_get_result($check_faculty_stmt);

if (mysqli_num_rows($faculty_result) === 0) {
    echo json_encode([
        "message" => "Faculty user not found with ID: $faculty_id",
        "status" => false
    ]);
    mysqli_stmt_close($check_faculty_stmt);
    mysqli_close($conn);
    exit;
}

$faculty_row = mysqli_fetch_assoc($faculty_result);
$faculty_institute_id = $faculty_row['institute_id'];
mysqli_stmt_close($check_faculty_stmt);

// ✅ Institute users can only update their own faculty members
if ($user_role === 'institute' && $faculty_institute_id !== $user_institute_id) {
    echo json_encode([
        "message" => "You do not have permission to update this faculty user",
        "status" => false
    ]);
    mysqli_close($conn);
    exit;
}

// ✅ Build dynamic UPDATE query
$update_fields = [];
$params = [];
$types = "";

// Check and add institute_id (only admin can change institute_id)
if (isset($data['institute_id']) && $user_role === 'admin') {
    $institute_id = intval($data['institute_id']);
    
    // ✅ Validate institute_id exists
    $check_institute_sql = "SELECT id FROM institute_profiles WHERE id = ?";
    $check_institute_stmt = mysqli_prepare($conn, $check_institute_sql);
    mysqli_stmt_bind_param($check_institute_stmt, "i", $institute_id);
    mysqli_stmt_execute($check_institute_stmt);
    $institute_result = mysqli_stmt_get_result($check_institute_stmt);
    
    if (mysqli_num_rows($institute_result) === 0) {
        echo json_encode([
            "message" => "Institute not found with ID: $institute_id",
            "status" => false
        ]);
        mysqli_stmt_close($check_institute_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_institute_stmt);
    
    $update_fields[] = "institute_id = ?";
    $params[] = $institute_id;
    $types .= "i";
}

// Check and add name
if (isset($data['name']) && !empty(trim($data['name']))) {
    $name = mysqli_real_escape_string($conn, trim($data['name']));
    $update_fields[] = "name = ?";
    $params[] = $name;
    $types .= "s";
}

// Check and add email
if (isset($data['email']) && !empty(trim($data['email']))) {
    $email = mysqli_real_escape_string($conn, trim($data['email']));
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "message" => "Invalid email format",
            "status" => false
        ]);
        exit;
    }
    
    // ✅ Check if email already exists for another user
    $check_email_sql = "SELECT id FROM faculty_users WHERE email = ? AND id != ?";
    $check_email_stmt = mysqli_prepare($conn, $check_email_sql);
    mysqli_stmt_bind_param($check_email_stmt, "si", $email, $faculty_id);
    mysqli_stmt_execute($check_email_stmt);
    $email_result = mysqli_stmt_get_result($check_email_stmt);
    
    if (mysqli_num_rows($email_result) > 0) {
        echo json_encode([
            "message" => "Email already exists for another user",
            "status" => false
        ]);
        mysqli_stmt_close($check_email_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_email_stmt);
    
    $update_fields[] = "email = ?";
    $params[] = $email;
    $types .= "s";
}

// Check and add phone
if (isset($data['phone'])) {
    $phone = !empty(trim($data['phone'])) ? mysqli_real_escape_string($conn, trim($data['phone'])) : null;
    $update_fields[] = "phone = ?";
    $params[] = $phone;
    $types .= "s";
}

// Check and add admin_action (only admin can change admin_action)
if (isset($data['admin_action']) && $user_role === 'admin') {
    $admin_action = mysqli_real_escape_string($conn, $data['admin_action']);
    
    // Validate admin_action enum values
    if (!in_array($admin_action, ['pending', 'approved', 'rejected'])) {
        echo json_encode([
            "message" => "Invalid admin_action value. Must be: pending, approved, or rejected",
            "status" => false
        ]);
        exit;
    }
    
    $update_fields[] = "admin_action = ?";
    $params[] = $admin_action;
    $types .= "s";
}

// ✅ If no fields to update
if (empty($update_fields)) {
    echo json_encode([
        "message" => "No fields to update",
        "status" => false
    ]);
    exit;
}

// ✅ Build and execute UPDATE query
$update_sql = "UPDATE faculty_users SET " . implode(', ', $update_fields) . " WHERE id = ?";
$params[] = $faculty_id;
$types .= "i";

$update_stmt = mysqli_prepare($conn, $update_sql);

if (!$update_stmt) {
    echo json_encode([
        "message" => "Database prepare error: " . mysqli_error($conn),
        "status" => false
    ]);
    exit;
}

// Bind parameters dynamically
mysqli_stmt_bind_param($update_stmt, $types, ...$params);

if (mysqli_stmt_execute($update_stmt)) {
    // ✅ Fetch updated faculty user data
    $get_sql = "SELECT id, institute_id, name, email, phone, admin_action 
                FROM faculty_users WHERE id = ?";
    $get_stmt = mysqli_prepare($conn, $get_sql);
    mysqli_stmt_bind_param($get_stmt, "i", $faculty_id);
    mysqli_stmt_execute($get_stmt);
    $result = mysqli_stmt_get_result($get_stmt);
    $faculty_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($get_stmt);
    
    echo json_encode([
        "message" => "Faculty user updated successfully",
        "status" => true,
        "data" => $faculty_user,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "message" => "Failed to update faculty user: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($update_stmt);
mysqli_close($conn);
?>