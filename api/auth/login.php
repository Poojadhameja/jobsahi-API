<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../db.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array("message" => "Only POST requests allowed", "status" => false));
    exit;
}

// Get and decode JSON data
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

// Validate required fields
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Email and password are required", "status" => false));
    exit;
}

$email = trim($data['email']);
$password = trim($data['password']);

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(array("message" => "Email and password cannot be empty", "status" => false));
    exit;
}

// Use prepared statements
$sql = "SELECT id, name, email, role, phone_number, is_verified, password 
        FROM users 
        WHERE email = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            if ($user['is_verified'] == 1) {
                // Create JWT payload
                $payload = [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'phone_number' => $user['phone_number'],
                    'iat' => time(),
                    'exp' => time() + JWT_EXPIRY
                ];
                
                $jwt_token = JWTHelper::generateJWT($payload, JWT_SECRET);
                
                // Remove password from response
                unset($user['password']);
                
                http_response_code(200);
                echo json_encode(array(
                    "message" => "Login successful", 
                    "status" => true, 
                    "user" => $user,
                    "token" => $jwt_token,
                    "expires_in" => JWT_EXPIRY
                ));
            } else {
                http_response_code(403);
                echo json_encode(array("message" => "Account not verified", "status" => false));
            }
        } else {
            http_response_code(401);
            echo json_encode(array("message" => "Invalid credentials", "status" => false));
        }
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "Invalid credentials", "status" => false));
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database query failed", "status" => false));
}

mysqli_close($conn);
?>
