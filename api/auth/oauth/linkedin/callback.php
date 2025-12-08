<?php
// linkedin/callback.php - LinkedIn OAuth Callback Handler

require_once '../../../cors.php';
require_once '../../../db.php';
require_once '../../../config/oauth_config.php';
require_once '../../../jwt_token/jwt_helper.php';
require_once '../../../helpers/oauth_helper.php';

// Get authorization code from LinkedIn
$code = isset($_GET['code']) ? $_GET['code'] : null;
$error = isset($_GET['error']) ? $_GET['error'] : null;
$state = isset($_GET['state']) ? $_GET['state'] : null;

// Handle error from LinkedIn
if ($error) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "LinkedIn OAuth error: " . $error
    ]);
    exit;
}

// Check if code is present
if (!$code) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Authorization code not received"
    ]);
    exit;
}

try {
    // Get user info from LinkedIn
    $userInfo = OAuthHelper::getLinkedInUserInfo($code);
    
    if (isset($userInfo['error'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Failed to get user info from LinkedIn",
            "error" => $userInfo['error'],
            "details" => isset($userInfo['response']) ? $userInfo['response'] : null,
            "full_error" => $userInfo,
            "debug_info" => [
                "code_received" => substr($code, 0, 20) . "...",
                "redirect_uri" => LINKEDIN_REDIRECT_URI,
                "client_id" => substr(LINKEDIN_CLIENT_ID, 0, 10) . "..."
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    if (!$userInfo['success'] || !$userInfo['email']) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid user data from LinkedIn"
        ]);
        exit;
    }
    
    $linkedin_id = $userInfo['id'];
    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? ($userInfo['first_name'] . ' ' . $userInfo['last_name'] ?? 'User');
    $first_name = $userInfo['first_name'] ?? '';
    $last_name = $userInfo['last_name'] ?? '';
    
    // Check if user exists by linkedin_id
    $check_sql = "SELECT id, user_name, email, role, phone_number, is_verified, password, auth_provider 
                  FROM users 
                  WHERE linkedin_id = ? OR email = ?";
    
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ss", $linkedin_id, $email);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $existing_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);
    
    $user_id = null;
    $is_new_user = false;
    
    if ($existing_user) {
        // User exists - Login
        $user_id = $existing_user['id'];
        
        // Update linkedin_id if not set
        if (!isset($existing_user['linkedin_id']) || empty($existing_user['linkedin_id'])) {
            $update_sql = "UPDATE users SET linkedin_id = ?, auth_provider = 'linkedin' WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $linkedin_id, $user_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
        
        $user = $existing_user;
    } else {
        // User doesn't exist - Create new user
        $is_new_user = true;
        
        // Generate a random phone number placeholder (OAuth users might not have phone)
        $phone_number = '0000000000';
        
        // Default role
        $role = 'student';
        $is_verified = 1; // OAuth users are auto-verified
        
        $insert_sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, linkedin_id, auth_provider, created_at, last_activity) 
                       VALUES (?, ?, NULL, ?, ?, ?, 'active', ?, 'linkedin', NOW(), NOW())";
        
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "ssssis", $name, $email, $phone_number, $role, $is_verified, $linkedin_id);
        
        if (!mysqli_stmt_execute($insert_stmt)) {
            throw new Exception("Failed to create user: " . mysqli_error($conn));
        }
        
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_stmt);
        
        // Fetch created user
        $user_sql = "SELECT id, user_name, email, role, phone_number, is_verified FROM users WHERE id = ?";
        $user_stmt = mysqli_prepare($conn, $user_sql);
        mysqli_stmt_bind_param($user_stmt, "i", $user_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user = mysqli_fetch_assoc($user_result);
        mysqli_stmt_close($user_stmt);
    }
    
    // Generate JWT token
    $payload = [
        'user_id' => $user_id,
        'email' => $user['email'],
        'name' => $user['user_name'],
        'role' => $user['role'],
        'phone_number' => $user['phone_number'],
        'iat' => time()
    ];
    
    $jwt_token = JWTHelper::generateJWT($payload, JWT_SECRET);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => $is_new_user ? "User registered and logged in successfully" : "Login successful",
        "user" => $user,
        "token" => $jwt_token,
        "is_new_user" => $is_new_user
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Internal server error: " . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>

