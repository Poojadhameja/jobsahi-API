<?php
// me.php - Fetch current logged-in user with role-aware profiles
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array("message" => "Only GET requests allowed", "status" => false));
    exit;
}

require_once '../jwt_token/jwt_helper.php';
require_once __DIR__ . '/../db.php';

// Get JWT token from Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(array("message" => "Authorization header required", "status" => false));
    exit;
}

// Extract token from "Bearer TOKEN" format
$token_parts = explode(' ', $authHeader);
if (count($token_parts) !== 2 || $token_parts[0] !== 'Bearer') {
    http_response_code(401);
    echo json_encode(array("message" => "Invalid authorization format", "status" => false));
    exit;
}

$jwt_token = $token_parts[1];

// Verify JWT token
$decoded_token = JWTHelper::verifyJWT($jwt_token, JWT_SECRET);

if (!$decoded_token) {
    http_response_code(401);
    echo json_encode(array("message" => "Invalid or expired token", "status" => false));
    exit;
}

$user_id = $decoded_token['user_id'];

// Fetch user basic information
$user_sql = "SELECT id, user_name, email, phone_number, role, is_verified 
             FROM users WHERE id = ?";

if ($user_stmt = mysqli_prepare($conn, $user_sql)) {
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    
    if (mysqli_num_rows($user_result) === 0) {
        http_response_code(404);
        echo json_encode(array("message" => "User not found", "status" => false));
        mysqli_stmt_close($user_stmt);
        mysqli_close($conn);
        exit;
    }
    
    $user_data = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    // Prepare response array with user basic info
    $response = array(
        "status" => true,
        "message" => "User data fetched successfully",
        "data" => array(
            "id" => (int)$user_data['id'],
            "name" => $user_data['name'],
            "email" => $user_data['email'],
            "phone_number" => $user_data['phone_number'],
            "role" => $user_data['role'],
            "is_verified" => (bool)$user_data['is_verified'],
            "profile" => null
        )
    );
    
    // Fetch role-specific profile data
    switch ($user_data['role']) {
        case 'student':
            $profile_sql = "SELECT sp.*, u.name as user_name 
                           FROM student_profiles sp 
                           JOIN users u ON sp.user_id = u.id 
                           WHERE sp.user_id = ?";
            
            if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                mysqli_stmt_execute($profile_stmt);
                $profile_result = mysqli_stmt_get_result($profile_stmt);
                
                if (mysqli_num_rows($profile_result) > 0) {
                    $profile_data = mysqli_fetch_assoc($profile_result);
                    $response['data']['profile'] = array(
                        "id" => (int)$profile_data['id'],
                        "bio" => $profile_data['bio'],
                        "skills" => $profile_data['skills'] ? json_decode($profile_data['skills'], true) : [],
                        "education" => $profile_data['education'],
                        "experience" => $profile_data['experience'],
                        "portfolio_url" => $profile_data['portfolio_url'],
                        "resume_url" => $profile_data['resume_url'],
                        "location" => $profile_data['location'],
                        "graduation_year" => $profile_data['graduation_year'],
                        "cgpa" => $profile_data['cgpa'] ? (float)$profile_data['cgpa'] : null,
                        "created_at" => $profile_data['created_at'],
                        "updated_at" => $profile_data['updated_at']
                    );
                }
                mysqli_stmt_close($profile_stmt);
            }
            break;
            
        case 'recruiter':
            $profile_sql = "SELECT rp.*, u.name as user_name 
                           FROM recruiter_profiles rp 
                           JOIN users u ON rp.user_id = u.id 
                           WHERE rp.user_id = ?";
            
            if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                mysqli_stmt_execute($profile_stmt);
                $profile_result = mysqli_stmt_get_result($profile_stmt);
                
                if (mysqli_num_rows($profile_result) > 0) {
                    $profile_data = mysqli_fetch_assoc($profile_result);
                    $response['data']['profile'] = array(
                        "id" => (int)$profile_data['id'],
                        "company_name" => $profile_data['company_name'],
                        "company_website" => $profile_data['company_website'],
                        "company_description" => $profile_data['company_description'],
                        "position" => $profile_data['position'],
                        "department" => $profile_data['department'],
                        "linkedin_url" => $profile_data['linkedin_url'],
                        "company_size" => $profile_data['company_size'],
                        "industry" => $profile_data['industry'],
                        "location" => $profile_data['location'],
                        "created_at" => $profile_data['created_at'],
                        "updated_at" => $profile_data['updated_at']
                    );
                }
                mysqli_stmt_close($profile_stmt);
            }
            break;
            
        case 'institute':
            $profile_sql = "SELECT ip.*, u.name as user_name 
                           FROM institute_profiles ip 
                           JOIN users u ON ip.user_id = u.id 
                           WHERE ip.user_id = ?";
            
            if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                mysqli_stmt_execute($profile_stmt);
                $profile_result = mysqli_stmt_get_result($profile_stmt);
                
                if (mysqli_num_rows($profile_result) > 0) {
                    $profile_data = mysqli_fetch_assoc($profile_result);
                    $response['data']['profile'] = array(
                        "id" => (int)$profile_data['id'],
                        "institute_name" => $profile_data['institute_name'],
                        "institute_type" => $profile_data['institute_type'],
                        "website" => $profile_data['website'],
                        "description" => $profile_data['description'],
                        "address" => $profile_data['address'],
                        "city" => $profile_data['city'],
                        "state" => $profile_data['state'],
                        "country" => $profile_data['country'],
                        "postal_code" => $profile_data['postal_code'],
                        "contact_person" => $profile_data['contact_person'],
                        "contact_designation" => $profile_data['contact_designation'],
                        "accreditation" => $profile_data['accreditation'],
                        "established_year" => $profile_data['established_year'] ? (int)$profile_data['established_year'] : null,
                        "created_at" => $profile_data['created_at'],
                        "updated_at" => $profile_data['updated_at']
                    );
                }
                mysqli_stmt_close($profile_stmt);
            }
            break;
            
        default:
            // For roles without specific profiles, profile remains null
            break;
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare failed: " . mysqli_error($conn), "status" => false));
}

mysqli_close($conn);
?>