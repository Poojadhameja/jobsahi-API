<?php
// login_phone.php - User authentication with JWT using phone number
require_once '../cors.php';

// Get phone number from URL path
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($request_uri, '/'));

// Extract phone_number from URL - get the last segment
$phone_number = null;
if (count($path_parts) > 0) {
    $last_segment = end($path_parts);
    // Check if last segment is a number (phone number)
    if (is_numeric($last_segment) && strlen($last_segment) >= 10) {
        $phone_number = trim($last_segment);
    }
}

if (empty($phone_number)) {
    http_response_code(400);
    echo json_encode(array("message" => "Phone number is required in URL", "status" => false));
    exit;
}

// Get and decode JSON data for password
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid JSON data", "status" => false));
    exit;
}

// Validate password field
if (!isset($data['password'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Password is required", "status" => false));
    exit;
}

$password = trim($data['password']);

if (empty($password)) {
    http_response_code(400);
    echo json_encode(array("message" => "Password cannot be empty", "status" => false));
    exit;
}

// Use prepared statements - Added status field
$sql = "SELECT id, user_name, email, role, phone_number, is_verified, status, password 
        FROM users 
        WHERE phone_number = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $phone_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            http_response_code(403);
            echo json_encode(array("message" => "Account is not active", "status" => false));
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        }
        
        if (password_verify($password, $user['password'])) {
            if ($user['is_verified'] == 1) {
                // Create JWT payload
                $payload = [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['user_name'],
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