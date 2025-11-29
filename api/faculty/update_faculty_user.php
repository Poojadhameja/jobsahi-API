<?php
// update_faculty_user.php - Update an existing faculty user
require_once '../cors.php';

// ✅ Authenticate and allow only "admin" and "institute"
$decoded = authenticateJWT(['admin','institute']); 

// Extract user_id and role
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

require_once '../db.php';

// ===============================================
// 🔍 DETERMINE institute_id BASED ON ROLE
// ===============================================
$user_institute_id = null;

if ($user_role === 'admin') {
    $fetch_sql = "SELECT id FROM institute_profiles WHERE id = ?";
    $stmt = mysqli_prepare($conn, $fetch_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $user_institute_id = $row['id'];
    }
    mysqli_stmt_close($stmt);
}

elseif ($user_role === 'institute') {
    $fetch_sql = "SELECT id FROM institute_profiles WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $fetch_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $user_institute_id = $row['id'];
    }
    mysqli_stmt_close($stmt);

    if ($user_institute_id === null) {
        echo json_encode([
            "status" => false,
            "message" => "Institute ID not found"
        ]);
        exit;
    }
}

// ===============================================
// 📥 READ INPUT
// ===============================================
$data = json_decode(file_get_contents('php://input'), true);

// Validate faculty_id
if (empty($data['id'])) {
    echo json_encode([
        "status" => false,
        "message" => "Faculty ID is required"
    ]);
    exit;
}

$faculty_id = intval($data['id']);

// ===============================================
// ✔ CHECK FACULTY EXISTS
// ===============================================
$check_sql = "SELECT institute_id FROM faculty_users WHERE id = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, "i", $faculty_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($res) === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Faculty user not found"
    ]);
    exit;
}

$row = mysqli_fetch_assoc($res);
$faculty_institute_id = $row['institute_id'];
mysqli_stmt_close($stmt);

// Institute can update only own faculty
if ($user_role === 'institute' && $faculty_institute_id !== $user_institute_id) {
    echo json_encode([
        "status" => false,
        "message" => "You cannot update this faculty"
    ]);
    exit;
}

// ===============================================
// ✅ VALIDATE EMAIL UNIQUENESS (if updating email)
// ===============================================
if (!empty(trim($data['email'] ?? ""))) {
    $email = trim($data['email']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid email format"
        ]);
        exit;
    }
    
    // Check if email already exists (excluding current faculty)
    $check_email_sql = "SELECT id FROM faculty_users WHERE email = ? AND id != ?";
    $check_email_stmt = mysqli_prepare($conn, $check_email_sql);
    mysqli_stmt_bind_param($check_email_stmt, "si", $email, $faculty_id);
    mysqli_stmt_execute($check_email_stmt);
    $email_result = mysqli_stmt_get_result($check_email_stmt);
    
    if (mysqli_num_rows($email_result) > 0) {
        echo json_encode([
            "status" => false,
            "message" => "Email already exists"
        ]);
        mysqli_stmt_close($check_email_stmt);
        exit;
    }
    mysqli_stmt_close($check_email_stmt);
}

// ===============================================
// ✅ VALIDATE PHONE UNIQUENESS (if updating phone)
// ===============================================
if (isset($data['phone']) && !empty(trim($data['phone'] ?? ""))) {
    $phone = trim($data['phone']);
    
    // Check if phone already exists (excluding current faculty)
    $check_phone_sql = "SELECT id FROM faculty_users WHERE phone = ? AND id != ?";
    $check_phone_stmt = mysqli_prepare($conn, $check_phone_sql);
    mysqli_stmt_bind_param($check_phone_stmt, "si", $phone, $faculty_id);
    mysqli_stmt_execute($check_phone_stmt);
    $phone_result = mysqli_stmt_get_result($check_phone_stmt);
    
    if (mysqli_num_rows($phone_result) > 0) {
        echo json_encode([
            "status" => false,
            "message" => "Phone number already exists"
        ]);
        mysqli_stmt_close($check_phone_stmt);
        exit;
    }
    mysqli_stmt_close($check_phone_stmt);
}

// ===============================================
// 🔧 BUILD UPDATE QUERY (NO admin_action)
// ===============================================
$update_fields = [];
$params = [];
$types = "";

// admin only: change institute_id
if (isset($data['institute_id']) && $user_role === 'admin') {
    $update_fields[] = "institute_id = ?";
    $params[] = intval($data['institute_id']);
    $types .= "i";
}

// name
if (!empty(trim($data['name'] ?? ""))) {
    $update_fields[] = "name = ?";
    $params[] = trim($data['name']);
    $types .= "s";
}

// email
if (!empty(trim($data['email'] ?? ""))) {
    $update_fields[] = "email = ?";
    $params[] = trim($data['email']);
    $types .= "s";
}

// phone
if (isset($data['phone'])) {
    $update_fields[] = "phone = ?";
    $params[] = trim($data['phone']);
    $types .= "s";
}

// role — both admin + institute can update
if (isset($data['role'])) {
    $role = trim($data['role']);
    if (!in_array($role, ['admin','faculty'])) {
        echo json_encode(["status" => false, "message" => "Invalid role"]);
        exit;
    }
    $update_fields[] = "role = ?";
    $params[] = $role;
    $types .= "s";
}

// ❌ REMOVED: admin_action logic completely

if (empty($update_fields)) {
    echo json_encode([
        "status" => false,
        "message" => "No fields to update"
    ]);
    exit;
}

$update_sql = "UPDATE faculty_users SET ".implode(', ', $update_fields)." WHERE id = ?";
$params[] = $faculty_id;
$types .= "i";

$stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// ===============================================
// 🔄 FETCH UPDATED DATA (WITHOUT admin_action)
// ===============================================
$get_sql = "
    SELECT id, institute_id, name, email, phone, role
    FROM faculty_users 
    WHERE id = ?
";
$stmt = mysqli_prepare($conn, $get_sql);
mysqli_stmt_bind_param($stmt, "i", $faculty_id);
mysqli_stmt_execute($stmt);
$updated = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// ===============================================
// ✅ FINAL RESPONSE
// ===============================================
echo json_encode([
    "status" => true,
    "message" => "Faculty user updated successfully",
    "data" => $updated,
    "timestamp" => date('Y-m-d H:i:s')
]);

?>
