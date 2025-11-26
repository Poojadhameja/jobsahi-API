<?php
// create_faculty_user.php - Create a new faculty user
require_once '../cors.php';

// ✅ Authenticate and allow only "admin" and "institute"
$decoded = authenticateJWT(['admin','institute']); 

// ✅ Extract user_id and role from token
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// ✅ Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// ✅ Determine institute_id based on user role
$institute_id = null;

if ($user_role === 'admin') {
    // Admin can specify institute_id or it can be fetched from their profile
    if (isset($data['institute_id']) && !empty($data['institute_id'])) {
        $institute_id = intval($data['institute_id']);
    } else {
        // Fetch institute_id from users table for admin
        $fetch_institute_sql = "SELECT institute_id FROM users WHERE id = ?";
        $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
        mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
        mysqli_stmt_execute($fetch_stmt);
        $fetch_result = mysqli_stmt_get_result($fetch_stmt);
        
        if ($row = mysqli_fetch_assoc($fetch_result)) {
            $institute_id = $row['institute_id'];
        }
        mysqli_stmt_close($fetch_stmt);
    }
} elseif ($user_role === 'institute') {
    // For institute role, fetch institute_id from institute_profiles table
    $fetch_institute_sql = "SELECT id FROM institute_profiles WHERE user_id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);
    
    if ($row = mysqli_fetch_assoc($fetch_result)) {
        $institute_id = $row['id'];
    }
    mysqli_stmt_close($fetch_stmt);
}

// ✅ Check if institute_id was found
if ($institute_id === null) {
    echo json_encode([
        "message" => "Unable to determine institute_id for the user",
        "status" => false
    ]);
    exit;
}

// ✅ Validate required fields (name, email are required)
$required_fields = ['name', 'email'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        "message" => "Missing required fields: " . implode(', ', $missing_fields),
        "status" => false
    ]);
    exit;
}

// ✅ Extract and sanitize data
$name = mysqli_real_escape_string($conn, trim($data['name']));
$email = mysqli_real_escape_string($conn, trim($data['email']));
$phone = isset($data['phone']) && !empty($data['phone']) ? mysqli_real_escape_string($conn, trim($data['phone'])) : null;


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

// ✅ Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "message" => "Invalid email format",
        "status" => false
    ]);
    exit;
}

// ✅ Check if email already exists
$check_email_sql = "SELECT id FROM faculty_users WHERE email = ?";
$check_stmt = mysqli_prepare($conn, $check_email_sql);
mysqli_stmt_bind_param($check_stmt, "s", $email);
mysqli_stmt_execute($check_stmt);
$email_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($email_result) > 0) {
    echo json_encode([
        "message" => "Email already exists",
        "status" => false
    ]);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_stmt);

// ✅ Insert faculty user (without password)
$insert_sql = "INSERT INTO faculty_users (institute_id, name, email, phone) 
               VALUES (?, ?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_sql);

if (!$insert_stmt) {
    echo json_encode([
        "message" => "Database prepare error: " . mysqli_error($conn),
        "status" => false
    ]);
    exit;
}

mysqli_stmt_bind_param($insert_stmt, "isss", 
    $institute_id, 
    $name, 
    $email, 
    $phone
);

if (mysqli_stmt_execute($insert_stmt)) {
    $faculty_id = mysqli_insert_id($conn);
    
    // ✅ Fetch created faculty user
    $get_sql = "SELECT id, institute_id, name, email, phone 
                FROM faculty_users WHERE id = ?";
    $get_stmt = mysqli_prepare($conn, $get_sql);
    mysqli_stmt_bind_param($get_stmt, "i", $faculty_id);
    mysqli_stmt_execute($get_stmt);
    $result = mysqli_stmt_get_result($get_stmt);
    $faculty_user = mysqli_fetch_assoc($result);
    
    echo json_encode([
        "message" => "Faculty user created successfully",
        "status" => true,
        "data" => $faculty_user,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    
    mysqli_stmt_close($get_stmt);
} else {
    echo json_encode([
        "message" => "Failed to create faculty user: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>