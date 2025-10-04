<?php
// register.php - User registration with password hashing and profile creation
require_once '../cors.php';

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
$required_fields = ['user_name', 'email', 'password', 'phone_number'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        echo json_encode(array("message" => ucfirst(str_replace('_', ' ', $field)) . " is required", "status" => false));
        exit;
    }
}

$user_name = trim($data['user_name']);
$email = trim($data['email']);
$password = trim($data['password']);
$phone_number = trim($data['phone_number']);
$role = isset($data['role']) ? trim($data['role']) : 'student'; // Default role
$is_verified = isset($data['is_verified']) ? (int)$data['is_verified'] : 0; // Default 0 (not verified)
$status = isset($data['status']) ? trim($data['status']) : 'active'; // Default status

// Validate role - only allow specific roles based on your enum
$allowed_roles = ['student', 'recruiter', 'institute', 'admin'];
if (!in_array($role, $allowed_roles)) {
    echo json_encode(array("message" => "Invalid role. Allowed roles: student, recruiter, institute, admin", "status" => false));
    exit;
}

// Validate status - only allow specific statuses based on your enum
$allowed_statuses = ['active', 'inactive'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(array("message" => "Invalid status. Allowed statuses: active, inactive", "status" => false));
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array("message" => "Invalid email format", "status" => false));
    exit;
}

// Password strength validation
if (strlen($password) < 6) {
    echo json_encode(array("message" => "Password must be at least 6 characters long", "status" => false));
    exit;
}

include "../db.php";

// Start transaction
mysqli_autocommit($conn, FALSE);

try {
    // ✅ Check if email already exists
    $check_sql = "SELECT id FROM users WHERE email = ?";
    if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            throw new Exception("Email already exists");
        }
        mysqli_stmt_close($check_stmt);
    }

    // ✅ Check if phone number already exists
    $check_phone_sql = "SELECT id FROM users WHERE phone_number = ?";
    if ($check_phone_stmt = mysqli_prepare($conn, $check_phone_sql)) {
        mysqli_stmt_bind_param($check_phone_stmt, "s", $phone_number);
        mysqli_stmt_execute($check_phone_stmt);
        $check_phone_result = mysqli_stmt_get_result($check_phone_stmt);
        
        if (mysqli_num_rows($check_phone_result) > 0) {
            throw new Exception("Phone number already exists");
        }
        mysqli_stmt_close($check_phone_stmt);
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user with hashed password - using exact column names from your database
    $sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind parameters: s=string, i=integer
        mysqli_stmt_bind_param($stmt, "sssssis", $user_name, $email, $hashed_password, $phone_number, $role, $is_verified, $status);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("User registration failed: " . mysqli_error($conn));
        }
        
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Create profile based on role
        $profile_created = false;
        $profile_id = null;
        
        switch ($role) {
            case 'student':
                $profile_sql = "INSERT INTO student_profiles (user_id, created_at, modified_at) VALUES (?, NOW(), NOW())";
                if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                    mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                    if (mysqli_stmt_execute($profile_stmt)) {
                        $profile_id = mysqli_insert_id($conn);
                        $profile_created = true;
                    }
                    mysqli_stmt_close($profile_stmt);
                }
                break;
                
            case 'recruiter':
                $profile_sql = "INSERT INTO recruiter_profiles (user_id, created_at, modified_at) VALUES (?, NOW(), NOW())";
                if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                    mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                    if (mysqli_stmt_execute($profile_stmt)) {
                        $profile_id = mysqli_insert_id($conn);
                        $profile_created = true;
                    }
                    mysqli_stmt_close($profile_stmt);
                }
                break;
                
            case 'institute':
                $profile_sql = "INSERT INTO institute_profiles (user_id, created_at, modified_at) VALUES (?, NOW(), NOW())";
                if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                    mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                    if (mysqli_stmt_execute($profile_stmt)) {
                        $profile_id = mysqli_insert_id($conn);
                        $profile_created = true;
                    }
                    mysqli_stmt_close($profile_stmt);
                }
                break;
                
            case 'admin':
                // Admin users typically don't need separate profile tables
                // but you can create one if needed
                $profile_created = true;
                $profile_id = null;
                break;
        }
        
        if (!$profile_created) {
            throw new Exception("Failed to create " . $role . " profile");
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        $response_data = array(
            "message" => "User registered successfully" . ($role !== 'admin' ? " with " . $role . " profile" : ""), 
            "status" => true,
            "user_id" => $user_id,
            "role" => $role,
            "user_name" => $user_name,
            "email" => $email,
            "is_verified" => $is_verified,
            //"status" => $status
        );
        
        // Only include profile_id if it exists
        if ($profile_id !== null) {
            $response_data["profile_id"] = $profile_id;
        }
        
        echo json_encode($response_data);
        
    } else {
        throw new Exception("Database prepare failed: " . mysqli_error($conn));
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(array("message" => $e->getMessage(), "status" => false));
}

// Reset autocommit and close connection
mysqli_autocommit($conn, TRUE);
mysqli_close($conn);
?>