<?php
// register.php - User registration with password hashing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array("message" => "Only POST requests allowed", "status" => false));
    exit;
}

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

// Check if JSON was decoded successfully
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

// Check if required fields exist
$required_fields = ['name', 'email', 'password', 'phone_number'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        echo json_encode(array("message" => ucfirst($field) . " is required", "status" => false));
        exit;
    }
}

$name = trim($data['name']);
$email = trim($data['email']);
$password = trim($data['password']);
$phone_number = trim($data['phone_number']);
$role = isset($data['role']) ? trim($data['role']) : 'student'; // Default role
$is_verified = isset($data['is_verified']) ? (int)$data['is_verified'] : 0; // Default 0 (not verified)
// Removed status variable since column doesn't exist in database

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array("message" => "Invalid email format", "status" => false));
    exit;
}

// Password strength validation (optional)
if (strlen($password) < 6) {
    echo json_encode(array("message" => "Password must be at least 6 characters long", "status" => false));
    exit;
}

include "../database/config.php";

// Check if user already exists
$check_sql = "SELECT id FROM users WHERE email = ?";
if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(array("message" => "Email already exists", "status" => false));
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_stmt);
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new user with hashed password
$sql = "INSERT INTO users (name, email, password, phone_number, role, is_verified) 
        VALUES (?, ?, ?, ?, ?, ?)";

if ($stmt = mysqli_prepare($conn, $sql)) {
    // Bind parameters: s=string, i=integer
    mysqli_stmt_bind_param($stmt, "sssssi", $name, $email, $hashed_password, $phone_number, $role, $is_verified);
    
    if (mysqli_stmt_execute($stmt)) {
        $user_id = mysqli_insert_id($conn);
        echo json_encode(array(
            "message" => "User registered successfully", 
            "status" => true,
            "user_id" => $user_id
        ));
    } else {
        echo json_encode(array("message" => "Registration failed: " . mysqli_error($conn), "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(array("message" => "Database prepare failed: " . mysqli_error($conn), "status" => false));
}

mysqli_close($conn);
?>