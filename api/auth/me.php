<?php
// me.php - Fetch current logged-in user with role-aware profiles
require_once '../cors.php';
require_once '../db.php';

// Get JWT token from Authorization header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(["message" => "Authorization header required", "status" => false]);
    exit;
}

// Extract token from "Bearer TOKEN" format
$token_parts = explode(' ', $authHeader);
if (count($token_parts) !== 2 || $token_parts[0] !== 'Bearer') {
    http_response_code(401);
    echo json_encode(["message" => "Invalid authorization format", "status" => false]);
    exit;
}

$jwt_token = $token_parts[1];

// Verify JWT token
$decoded_token = JWTHelper::verifyJWT($jwt_token, JWT_SECRET);

if (!$decoded_token) {
    http_response_code(401);
    echo json_encode(["message" => "Invalid or expired token", "status" => false]);
    exit;
}

$user_id = $decoded_token['user_id'];

// ✅ Base URL setup for media paths
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

$resume_base = $protocol . $host . "/jobsahi-API/api/uploads/resume/";
$company_logo_base = $protocol . $host . "/jobsahi-API/api/uploads/recruiter_logo/";
$institute_logo_base = $protocol . $host . "/jobsahi-API/api/uploads/institute_logo/";

// Fetch user basic information
$user_sql = "SELECT id, user_name, email, phone_number, role, is_verified 
             FROM users WHERE id = ?";

if ($user_stmt = mysqli_prepare($conn, $user_sql)) {
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    
    if (mysqli_num_rows($user_result) === 0) {
        http_response_code(404);
        echo json_encode(["message" => "User not found", "status" => false]);
        mysqli_stmt_close($user_stmt);
        mysqli_close($conn);
        exit;
    }
    
    $user_data = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
    
    // Base response
    $response = [
        "status" => true,
        "message" => "User data fetched successfully",
        "data" => [
            "id" => (int)$user_data['id'],
            "user_name" => $user_data['user_name'],
            "email" => $user_data['email'],
            "phone_number" => $user_data['phone_number'],
            "role" => $user_data['role'],
            "is_verified" => (bool)$user_data['is_verified'],
            "profile" => null
        ]
    ];
    
    // =====================================================
    // ✅ Role-based profile retrieval
    // =====================================================
    switch ($user_data['role']) {
        // -------------------------------------------------
        // 🎓 STUDENT
        // -------------------------------------------------
        case 'student':
            $profile_sql = "SELECT sp.*, u.user_name as user_name 
                            FROM student_profiles sp 
                            JOIN users u ON sp.user_id = u.id 
                            WHERE sp.user_id = ?";

            if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                mysqli_stmt_execute($profile_stmt);
                $profile_result = mysqli_stmt_get_result($profile_stmt);
                
                if (mysqli_num_rows($profile_result) > 0) {
                    $profile_data = mysqli_fetch_assoc($profile_result);

                    // ✅ Build resume URL (R2 support)
                    $resume_url = null;
                    if (!empty($profile_data['resume'])) {
                        // ✅ Check if it's R2 URL
                        if (strpos($profile_data['resume'], 'http') === 0 && 
                            (strpos($profile_data['resume'], 'r2.dev') !== false || 
                             strpos($profile_data['resume'], 'r2.cloudflarestorage.com') !== false)) {
                            // ✅ Already R2 URL - use directly
                            $resume_url = $profile_data['resume'];
                        } else {
                            // ✅ Old local file path - construct server URL (backward compatibility)
                            $clean_resume = str_replace(["\\", "/uploads/resume/", "./", "../"], "", $profile_data['resume']);
                            $resume_local = __DIR__ . '/../uploads/resume/' . $clean_resume;
                            if (file_exists($resume_local)) {
                                $resume_url = $resume_base . $clean_resume;
                            }
                        }
                    }

                    $response['data']['profile'] = [
                        "id" => (int)($profile_data['id'] ?? 0),
                        "user_name" => $profile_data['user_name'] ?? null,
                        "skills" => $profile_data['skills'] ?? null,
                        "bio" => $profile_data['bio'] ?? null,
                        "portfolio_link" => $profile_data['portfolio_link'] ?? null,
                        "resume" => $resume_url,
                        "location" => $profile_data['location'] ?? null,
                        "education" => $profile_data['education'] ?? null,
                        "experience" => $profile_data['experience'] ?? null,
                        "graduation_year" => isset($profile_data['graduation_year']) ? (int)$profile_data['graduation_year'] : null,
                        "cgpa" => isset($profile_data['cgpa']) ? (float)$profile_data['cgpa'] : null,
                        "created_at" => $profile_data['created_at'] ?? null,
                        "modified_at" => $profile_data['modified_at'] ?? null
                    ];
                }
                mysqli_stmt_close($profile_stmt);
            }
            break;

        // -------------------------------------------------
        // 🏢 RECRUITER
        // -------------------------------------------------
        case 'recruiter':
            $profile_sql = "SELECT rp.*, u.user_name as user_name 
                            FROM recruiter_profiles rp 
                            JOIN users u ON rp.user_id = u.id 
                            WHERE rp.user_id = ?";
            
            if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                mysqli_stmt_execute($profile_stmt);
                $profile_result = mysqli_stmt_get_result($profile_stmt);
                
                if (mysqli_num_rows($profile_result) > 0) {
                    $profile_data = mysqli_fetch_assoc($profile_result);

                    // ✅ Build company_logo URL (R2 support)
                    $company_logo_url = null;
                    if (!empty($profile_data['company_logo'])) {
                        // ✅ Check if it's R2 URL
                        if (strpos($profile_data['company_logo'], 'http') === 0 && 
                            (strpos($profile_data['company_logo'], 'r2.dev') !== false || 
                             strpos($profile_data['company_logo'], 'r2.cloudflarestorage.com') !== false)) {
                            // ✅ Already R2 URL - use directly
                            $company_logo_url = $profile_data['company_logo'];
                        } else {
                            // ✅ Old local file path - construct server URL (backward compatibility)
                            $clean_logo = str_replace(["\\", "/uploads/recruiter_logo/", "./", "../"], "", $profile_data['company_logo']);
                            $logo_local = __DIR__ . '/../uploads/recruiter_logo/' . $clean_logo;
                            if (file_exists($logo_local)) {
                                $company_logo_url = $company_logo_base . $clean_logo;
                            }
                        }
                    }

                    $response['data']['profile'] = [
                        "id" => (int)($profile_data['id'] ?? 0),
                        "user_id" => (int)($profile_data['user_id'] ?? 0),
                        "company_name" => $profile_data['company_name'] ?? null,
                        "company_logo" => $company_logo_url,
                        "industry" => $profile_data['industry'] ?? null,
                        "website" => $profile_data['website'] ?? null,
                        "location" => $profile_data['location'] ?? null,
                        "created_at" => $profile_data['created_at'] ?? null,
                        "modified_at" => $profile_data['modified_at'] ?? null,
                        "deleted_at" => $profile_data['deleted_at'] ?? null,
                        "admin_action" => $profile_data['admin_action'] ?? null
                    ];
                }
                mysqli_stmt_close($profile_stmt);
            }
            break;

        // -------------------------------------------------
        // 🏫 INSTITUTE
        // -------------------------------------------------
        case 'institute':
            $profile_sql = "SELECT ip.*, u.user_name as user_name 
                            FROM institute_profiles ip 
                            JOIN users u ON ip.user_id = u.id 
                            WHERE ip.user_id = ?";
            
            if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                mysqli_stmt_bind_param($profile_stmt, "i", $user_id);
                mysqli_stmt_execute($profile_stmt);
                $profile_result = mysqli_stmt_get_result($profile_stmt);
                
                if (mysqli_num_rows($profile_result) > 0) {
                    $profile_data = mysqli_fetch_assoc($profile_result);

                    // ✅ Build institute_logo URL (R2 support)
                    $institute_logo_url = null;
                    if (!empty($profile_data['institute_logo'])) {
                        // ✅ Check if it's R2 URL
                        if (strpos($profile_data['institute_logo'], 'http') === 0 && 
                            (strpos($profile_data['institute_logo'], 'r2.dev') !== false || 
                             strpos($profile_data['institute_logo'], 'r2.cloudflarestorage.com') !== false)) {
                            // ✅ Already R2 URL - use directly
                            $institute_logo_url = $profile_data['institute_logo'];
                        } else {
                            // ✅ Old local file path - construct server URL (backward compatibility)
                            $clean_logo = str_replace(["\\", "/uploads/institute_logo/", "./", "../"], "", $profile_data['institute_logo']);
                            $logo_local = __DIR__ . '/../uploads/institute_logo/' . $clean_logo;
                            if (file_exists($logo_local)) {
                                $institute_logo_url = $institute_logo_base . $clean_logo;
                            }
                        }
                    }

                    $response['data']['profile'] = [
                        "id" => (int)($profile_data['id'] ?? 0),
                        "institute_name" => $profile_data['institute_name'] ?? null,
                        "institute_type" => $profile_data['institute_type'] ?? null,
                        "institute_logo" => $institute_logo_url,
                        "website" => $profile_data['website'] ?? null,
                        "description" => $profile_data['description'] ?? null,
                        "address" => $profile_data['address'] ?? null,
                        "city" => $profile_data['city'] ?? null,
                        "state" => $profile_data['state'] ?? null,
                        "country" => $profile_data['country'] ?? null,
                        "postal_code" => $profile_data['postal_code'] ?? null,
                        "contact_person" => $profile_data['contact_person'] ?? null,
                        "contact_designation" => $profile_data['contact_designation'] ?? null,
                        "accreditation" => $profile_data['accreditation'] ?? null,
                        "established_year" => isset($profile_data['established_year']) ? (int)$profile_data['established_year'] : null,
                        "created_at" => $profile_data['created_at'] ?? null,
                        "modified_at" => $profile_data['modified_at'] ?? null,
                        "deleted_at" => $profile_data['deleted_at'] ?? null,
                        "admin_action" => $profile_data['admin_action'] ?? null
                    ];
                }
                mysqli_stmt_close($profile_stmt);
            }
            break;
            
        default:
            // Other roles - profile remains null
            break;
    }

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database prepare failed: " . mysqli_error($conn), "status" => false]);
}

mysqli_close($conn);
?>
