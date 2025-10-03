<?php
// create_faculty_user.php - Create a new faculty user
require_once '../cors.php';

// ✅ Authenticate and allow only "admin"
$decoded = authenticateJWT(['admin']); 

// ✅ Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// ✅ Validate required fields
$required_fields = ['institute_id', 'name', 'email', 'password'];
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
$institute_id = intval($data['institute_id']);
$name = mysqli_real_escape_string($conn, trim($data['name']));
$email = mysqli_real_escape_string($conn, trim($data['email']));
$password = $data['password'];
$phone = isset($data['phone']) ? mysqli_real_escape_string($conn, trim($data['phone'])) : null;
$role = 'faculty'; // Fixed role
$admin_action = isset($data['admin_action']) ? mysqli_real_escape_string($conn, $data['admin_action']) : 'approved';

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

// ✅ Hash password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// ✅ Insert faculty user
$insert_sql = "INSERT INTO faculty_users (institute_id, name, email, phone, password, role, admin_action) 
               VALUES (?, ?, ?, ?, ?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_sql);

if (!$insert_stmt) {
    echo json_encode([
        "message" => "Database prepare error: " . mysqli_error($conn),
        "status" => false
    ]);
    exit;
}

mysqli_stmt_bind_param($insert_stmt, "issssss", 
    $institute_id, 
    $name, 
    $email, 
    $phone, 
    $hashed_password, 
    $role, 
    $admin_action
);

if (mysqli_stmt_execute($insert_stmt)) {
    $faculty_id = mysqli_insert_id($conn);
    
    // ✅ Fetch created faculty user
    $get_sql = "SELECT id, institute_id, name, email, phone, role, admin_action 
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