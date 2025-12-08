<?php
// google/callback.php - Google OAuth Callback Handler

require_once '../../../cors.php';
require_once '../../../db.php';
require_once '../../../config/oauth_config.php';
require_once '../../../jwt_token/jwt_helper.php';
require_once '../../../helpers/oauth_helper.php';

// Get parameters from Google OAuth
$code = isset($_GET['code']) ? urldecode($_GET['code']) : null;
$idToken = isset($_GET['id_token']) ? $_GET['id_token'] : null;
$accessToken = isset($_GET['access_token']) ? $_GET['access_token'] : null; // For Flutter Web
$error = isset($_GET['error']) ? $_GET['error'] : null;
$state = isset($_GET['state']) ? $_GET['state'] : null;

// Handle error from Google
if ($error) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Google OAuth error: " . $error
    ]);
    exit;
}

// Check if any valid parameter is present
if (!$code && !$idToken && !$accessToken) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Authorization code, ID token, or access token not received"
    ]);
    exit;
}

try {
    // Get user info from Google based on provided parameter
    if ($accessToken) {
        // Flutter Web sends access_token directly
        $userInfo = OAuthHelper::getGoogleUserInfoFromAccessToken($accessToken);
    } elseif ($code) {
        // Standard OAuth flow with authorization code
        $userInfo = OAuthHelper::getGoogleUserInfo($code);
    } elseif ($idToken) {
        // ID token flow (if implemented)
        // For now, decode ID token or use access token flow
        // You can implement ID token verification here if needed
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "ID token flow not yet implemented. Please use code or access_token."
        ]);
        exit;
    }
    
    if (isset($userInfo['error'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Failed to get user info from Google",
            "error" => $userInfo['error'],
            "details" => isset($userInfo['response']) ? $userInfo['response'] : null,
            "full_error" => $userInfo,
            "debug_info" => [
                "code_received" => substr($code, 0, 20) . "...",
                "redirect_uri" => GOOGLE_REDIRECT_URI,
                "client_id" => substr(GOOGLE_CLIENT_ID, 0, 30) . "..."
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    if (!$userInfo['success'] || !$userInfo['email']) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid user data from Google"
        ]);
        exit;
    }
    
    $google_id = $userInfo['id'];
    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? ($userInfo['first_name'] . ' ' . $userInfo['last_name'] ?? 'User');
    $first_name = $userInfo['first_name'] ?? '';
    $last_name = $userInfo['last_name'] ?? '';
    
    // Check if user exists by google_id
    // Check if auth_provider column exists
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'auth_provider'");
    $hasAuthProvider = mysqli_num_rows($checkColumn) > 0;
    
    if ($hasAuthProvider) {
        $check_sql = "SELECT id, user_name, email, role, phone_number, is_verified, password, auth_provider 
                      FROM users 
                      WHERE google_id = ? OR email = ?";
    } else {
        $check_sql = "SELECT id, user_name, email, role, phone_number, is_verified, password 
                      FROM users 
                      WHERE email = ?";
    }
    
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if ($hasAuthProvider) {
        mysqli_stmt_bind_param($check_stmt, "ss", $google_id, $email);
    } else {
        mysqli_stmt_bind_param($check_stmt, "s", $email);
    }
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $existing_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);
    
    $user_id = null;
    $is_new_user = false;
    
    if ($existing_user) {
        // User exists - Login
        $user_id = $existing_user['id'];
        
        // Update google_id if not set
        if (!isset($existing_user['google_id']) || empty($existing_user['google_id'])) {
            if ($hasAuthProvider) {
                $update_sql = "UPDATE users SET google_id = ?, auth_provider = 'google' WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $google_id, $user_id);
            } else {
                // Try to add google_id column if it doesn't exist
                @mysqli_query($conn, "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER password");
                $update_sql = "UPDATE users SET google_id = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $google_id, $user_id);
            }
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
        
        // Check if columns exist before inserting
        if ($hasAuthProvider) {
            $insert_sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, google_id, auth_provider, created_at, last_activity) 
                           VALUES (?, ?, NULL, ?, ?, ?, 'active', ?, 'google', NOW(), NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ssssis", $name, $email, $phone_number, $role, $is_verified, $google_id);
        } else {
            // Try to add missing columns first
            @mysqli_query($conn, "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER password");
            @mysqli_query($conn, "ALTER TABLE users ADD COLUMN auth_provider ENUM('email', 'google', 'linkedin') DEFAULT 'email' AFTER google_id");
            @mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL");
            
            // Re-check if columns exist
            $checkColumnAgain = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'auth_provider'");
            $hasAuthProviderNow = mysqli_num_rows($checkColumnAgain) > 0;
            
            if ($hasAuthProviderNow) {
                $insert_sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, google_id, auth_provider, created_at, last_activity) 
                               VALUES (?, ?, NULL, ?, ?, ?, 'active', ?, 'google', NOW(), NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "ssssis", $name, $email, $phone_number, $role, $is_verified, $google_id);
            } else {
                // Fallback: insert without OAuth fields
                $insert_sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, created_at, last_activity) 
                               VALUES (?, ?, NULL, ?, ?, ?, 'active', NOW(), NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "ssssi", $name, $email, $phone_number, $role, $is_verified);
            }
        }
        
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

