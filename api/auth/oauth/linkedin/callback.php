<?php
// linkedin/callback.php - LinkedIn OAuth Callback Handler

require_once '../../../cors.php';
require_once '../../../db.php';
require_once '../../../config/oauth_config.php';
require_once '../../../jwt_token/jwt_helper.php';
require_once '../../../helpers/oauth_helper.php';

// Get authorization code from LinkedIn
// Support both GET (web redirect) and POST (Flutter app)
$json_input = file_get_contents('php://input');
$post_data = json_decode($json_input, true);
$code = isset($_GET['code']) ? $_GET['code'] : (isset($post_data['code']) ? $post_data['code'] : null);
$accessToken = isset($_GET['access_token']) ? $_GET['access_token'] : (isset($post_data['access_token']) ? $post_data['access_token'] : null);
$error = isset($_GET['error']) ? $_GET['error'] : (isset($post_data['error']) ? $post_data['error'] : null);
$state = isset($_GET['state']) ? $_GET['state'] : (isset($post_data['state']) ? $post_data['state'] : null);

// Handle error from LinkedIn
if ($error) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "LinkedIn OAuth error: " . $error
    ]);
    exit;
}

// Check if any valid parameter is present
if (!$code && !$accessToken) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Authorization code or access token not received"
    ]);
    exit;
}

try {
    // Get user info from LinkedIn based on provided parameter
    if ($accessToken) {
        // Flutter/Mobile apps send access_token directly
        // For LinkedIn, we need to implement getLinkedInUserInfoFromAccessToken
        // For now, use code flow
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Access token flow for LinkedIn not yet implemented. Please use authorization code flow."
        ]);
        exit;
    } elseif ($code) {
        // Standard OAuth flow with authorization code
        $userInfo = OAuthHelper::getLinkedInUserInfo($code);
    }
    
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
    
    // Check if auth_provider column exists
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'auth_provider'");
    $hasAuthProvider = mysqli_num_rows($checkColumn) > 0;
    
    // Check if user exists by linkedin_id
    if ($hasAuthProvider) {
        $check_sql = "SELECT id, user_name, email, role, phone_number, is_verified, password, auth_provider 
                      FROM users 
                      WHERE linkedin_id = ? OR email = ?";
    } else {
        $check_sql = "SELECT id, user_name, email, role, phone_number, is_verified, password 
                      FROM users 
                      WHERE email = ?";
    }
    
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if ($hasAuthProvider) {
        mysqli_stmt_bind_param($check_stmt, "ss", $linkedin_id, $email);
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
        
        // Update linkedin_id if not set
        if (!isset($existing_user['linkedin_id']) || empty($existing_user['linkedin_id'])) {
            if ($hasAuthProvider) {
                $update_sql = "UPDATE users SET linkedin_id = ?, auth_provider = 'linkedin' WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $linkedin_id, $user_id);
            } else {
                // Try to add linkedin_id column if it doesn't exist
                @mysqli_query($conn, "ALTER TABLE users ADD COLUMN linkedin_id VARCHAR(255) NULL AFTER password");
                @mysqli_query($conn, "ALTER TABLE users ADD COLUMN auth_provider ENUM('email', 'google', 'linkedin') DEFAULT 'email' AFTER linkedin_id");
                $update_sql = "UPDATE users SET linkedin_id = ?, auth_provider = 'linkedin' WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $linkedin_id, $user_id);
            }
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
        
        $user = $existing_user;
        
        // ✅ For existing users, also check if student_profiles entry exists (if role is student)
        if ($existing_user['role'] === 'student') {
            $check_profile_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
            $check_profile_stmt = mysqli_prepare($conn, $check_profile_sql);
            mysqli_stmt_bind_param($check_profile_stmt, "i", $user_id);
            mysqli_stmt_execute($check_profile_stmt);
            $profile_result = mysqli_stmt_get_result($check_profile_stmt);
            
            if (mysqli_num_rows($profile_result) === 0) {
                // Create student profile entry for existing user
                $profile_sql = "INSERT INTO student_profiles 
                    (user_id, created_at, updated_at)
                    VALUES (?, NOW(), NOW())";
                $profile_stmt = mysqli_prepare($conn, $profile_sql);
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                
                if (!mysqli_stmt_execute($profile_stmt)) {
                    // Log error but don't fail the login
                    error_log("Failed to create student profile for existing user_id: $user_id - " . mysqli_error($conn));
                }
                mysqli_stmt_close($profile_stmt);
            }
            mysqli_stmt_close($check_profile_stmt);
        }
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
            $insert_sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, linkedin_id, auth_provider, created_at, last_activity) 
                           VALUES (?, ?, NULL, ?, ?, ?, 'active', ?, 'linkedin', NOW(), NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ssssis", $name, $email, $phone_number, $role, $is_verified, $linkedin_id);
        } else {
            // Try to add missing columns first
            @mysqli_query($conn, "ALTER TABLE users ADD COLUMN linkedin_id VARCHAR(255) NULL AFTER password");
            @mysqli_query($conn, "ALTER TABLE users ADD COLUMN auth_provider ENUM('email', 'google', 'linkedin') DEFAULT 'email' AFTER linkedin_id");
            @mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL");
            
            // Re-check if columns exist
            $checkColumnAgain = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'auth_provider'");
            $hasAuthProviderNow = mysqli_num_rows($checkColumnAgain) > 0;
            
            if ($hasAuthProviderNow) {
                $insert_sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, linkedin_id, auth_provider, created_at, last_activity) 
                               VALUES (?, ?, NULL, ?, ?, ?, 'active', ?, 'linkedin', NOW(), NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "ssssis", $name, $email, $phone_number, $role, $is_verified, $linkedin_id);
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
        
        // ✅ Create student_profiles entry if role is 'student'
        if ($role === 'student') {
            // Check if student_profiles entry already exists
            $check_profile_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
            $check_profile_stmt = mysqli_prepare($conn, $check_profile_sql);
            mysqli_stmt_bind_param($check_profile_stmt, "i", $user_id);
            mysqli_stmt_execute($check_profile_stmt);
            $profile_result = mysqli_stmt_get_result($check_profile_stmt);
            
            if (mysqli_num_rows($profile_result) === 0) {
                // Create student profile entry
                $profile_sql = "INSERT INTO student_profiles 
                    (user_id, created_at, updated_at)
                    VALUES (?, NOW(), NOW())";
                $profile_stmt = mysqli_prepare($conn, $profile_sql);
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                
                if (!mysqli_stmt_execute($profile_stmt)) {
                    // Log error but don't fail the login
                    error_log("Failed to create student profile for user_id: $user_id - " . mysqli_error($conn));
                }
                mysqli_stmt_close($profile_stmt);
            }
            mysqli_stmt_close($check_profile_stmt);
        }
        
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

