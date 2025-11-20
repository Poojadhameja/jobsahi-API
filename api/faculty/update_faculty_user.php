<?php
// update_faculty_user.php - Update an existing faculty user
require_once '../cors.php';

// ✅ Authenticate and allow only "admin" and "institute"
$decoded = authenticateJWT(['admin','institute']); 

// ✅ Extract user_id and role from token
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// DB connection
require_once '../db.php';

// ✅ Determine institute_id based on user role
$user_institute_id = null;

if ($user_role === 'admin') {
    $fetch_institute_sql = "SELECT id FROM institute_profiles WHERE id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);
    
    if ($row = mysqli_fetch_assoc($fetch_result)) {
        $user_institute_id = $row['id'];
    }
    mysqli_stmt_close($fetch_stmt);
} elseif ($user_role === 'institute') {
    $fetch_institute_sql = "SELECT id FROM institute_profiles WHERE user_id = ?";
    $fetch_stmt = mysqli_prepare($conn, $fetch_institute_sql);
    mysqli_stmt_bind_param($fetch_stmt, "i", $user_id);
    mysqli_stmt_execute($fetch_stmt);
    $fetch_result = mysqli_stmt_get_result($fetch_stmt);
    
    if ($row = mysqli_fetch_assoc($fetch_result)) {
        $user_institute_id = $row['id'];
    }
    mysqli_stmt_close($fetch_stmt);
    
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

// ✅ Validate required faculty_id
if (empty($data['id'])) {
    echo json_encode([
        "message" => "Faculty ID is required",
        "status" => false
    ]);
    exit;
}

$faculty_id = intval($data['id']);

// Check if faculty user exists
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
    exit;
}

$faculty_row = mysqli_fetch_assoc($faculty_result);
$faculty_institute_id = $faculty_row['institute_id'];
mysqli_stmt_close($check_faculty_stmt);

// institute can update only own faculty
if ($user_role === 'institute' && $faculty_institute_id !== $user_institute_id) {
    echo json_encode([
        "message" => "You do not have permission to update this faculty user",
        "status" => false
    ]);
    exit;
}

// --------------------------------------------------------
// BUILD UPDATE QUERY
// --------------------------------------------------------
$update_fields = [];
$params = [];
$types = "";

// institute_id (admin only)
if (isset($data['institute_id']) && $user_role === 'admin') {
    $institute_id = intval($data['institute_id']);
    $update_fields[] = "institute_id = ?";
    $params[] = $institute_id;
    $types .= "i";
}

// name
if (isset($data['name']) && trim($data['name']) !== "") {
    $name = trim($data['name']);
    $update_fields[] = "name = ?";
    $params[] = $name;
    $types .= "s";
}

// email
if (isset($data['email']) && trim($data['email']) !== "") {
    $email = trim($data['email']);
    $update_fields[] = "email = ?";
    $params[] = $email;
    $types .= "s";
}

// phone
if (isset($data['phone'])) {
    $phone = trim($data['phone']);
    $update_fields[] = "phone = ?";
    $params[] = $phone;
    $types .= "s";
}

// --------------------------------------------------------
// ⭐ UPDATED: NOW BOTH ADMIN + INSTITUTE CAN CHANGE ROLE
// --------------------------------------------------------
if (isset($data['role'])) {
    $role = trim($data['role']);

    // Allowed roles — you can add more if needed
    if (!in_array($role, ['admin', 'faculty'])) {
    echo json_encode([
        "message" => "Invalid role value",
        "status" => false
    ]);
    exit;
}

    $update_fields[] = "role = ?";
    $params[] = $role;
    $types .= "s";
}

// admin_action (admin only)
if (isset($data['admin_action']) && $user_role === 'admin') {
    $admin_action = trim($data['admin_action']);
    $update_fields[] = "admin_action = ?";
    $params[] = $admin_action;
    $types .= "s";
}

if (empty($update_fields)) {
    echo json_encode([
        "message" => "No fields to update",
        "status" => false
    ]);
    exit;
}

$update_sql = "UPDATE faculty_users SET " . implode(', ', $update_fields) . " WHERE id = ?";
$params[] = $faculty_id;
$types .= "i";

$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, $types, ...$params);
mysqli_stmt_execute($update_stmt);

// --------------------------------------------------------
// FETCH UPDATED DATA
// --------------------------------------------------------
$get_sql = "SELECT id, institute_id, name, email, phone, role, admin_action 
            FROM faculty_users WHERE id = ?";
$get_stmt = mysqli_prepare($conn, $get_sql);
mysqli_stmt_bind_param($get_stmt, "i", $faculty_id);
mysqli_stmt_execute($get_stmt);
$updated = mysqli_fetch_assoc(mysqli_stmt_get_result($get_stmt));

// --------------------------------------------------------
// FINAL RESPONSE FORMAT EXACT AS YOU WANT
// --------------------------------------------------------
echo json_encode([
    "message" => "Faculty user updated successfully",
    "status" => true,
    "data" => $updated,
    "timestamp" => date('Y-m-d H:i:s')
]);

?>
