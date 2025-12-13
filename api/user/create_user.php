<?php 
// create_user.php - User registration with password hashing and complete profile creation
// Handles both JSON and multipart/form-data
require_once '../cors.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array("message" => "Only POST requests allowed", "status" => false));
    exit;
}

// Check if data is multipart/form-data or JSON
$is_multipart = isset($_FILES) && !empty($_FILES) || isset($_POST['user_name']);
$data = array();

if ($is_multipart) {
    $data = $_POST;
} else {
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(array("message" => "Invalid JSON data", "status" => false));
        exit;
    }
}

// Required fields (password is optional for OAuth users)
$required_fields = ['user_name', 'email', 'phone_number', 'role'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        echo json_encode(array("message" => ucfirst(str_replace('_', ' ', $field)) . " is required", "status" => false));
        exit;
    }
}

$user_name = trim($data['user_name']);
$email = trim($data['email']);
$password = isset($data['password']) ? trim($data['password']) : null; // Optional for OAuth users
$phone_number = trim($data['phone_number']);
$role = isset($data['role']) ? trim($data['role']) : 'student';
$is_verified = isset($data['is_verified']) ? (int)$data['is_verified'] : 0;
$status = isset($data['status']) ? trim($data['status']) : 'active';
$auth_provider = isset($data['auth_provider']) ? trim($data['auth_provider']) : 'email';
$google_id = isset($data['google_id']) ? trim($data['google_id']) : null;
$linkedin_id = isset($data['linkedin_id']) ? trim($data['linkedin_id']) : null;

// Validate role
$allowed_roles = ['student', 'recruiter', 'institute', 'admin'];
if (!in_array($role, $allowed_roles)) {
    echo json_encode(array("message" => "Invalid role. Allowed roles: student, recruiter, institute, admin", "status" => false));
    exit;
}

// Validate status
$allowed_statuses = ['active', 'inactive'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(array("message" => "Invalid status. Allowed statuses: active, inactive", "status" => false));
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array("message" => "Invalid email format", "status" => false));
    exit;
}

// Validate password length (only if password is provided)
if ($password !== null && strlen($password) < 6) {
    echo json_encode(array("message" => "Password must be at least 6 characters long", "status" => false));
    exit;
}

// Password is required for email auth, optional for OAuth
if ($auth_provider === 'email' && $password === null) {
    echo json_encode(array("message" => "Password is required for email authentication", "status" => false));
    exit;
}

include "../db.php";

// Start transaction
mysqli_autocommit($conn, FALSE);

// File upload handler - Updated for R2
require_once __DIR__ . '/../helpers/r2_uploader.php';

function handleFileUpload($file_key, $upload_dir = '../uploads/') {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$file_key];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    if (!in_array($file['type'], $allowed_types)) {
        return null;
    }

    // ✅ Upload to R2 for company_logo (recruiter registration)
    if ($file_key === 'company_logo') {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $r2Path = "company_logos/logo_" . uniqid() . "_" . time() . '.' . $ext;
        $uploadResult = R2Uploader::uploadFile($file['tmp_name'], $r2Path);
        
        if ($uploadResult['success']) {
            return $uploadResult['url']; // Return R2 URL
        }
        return null;
    }

    // ✅ Fallback to local storage for other files (backward compatibility)
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '_' . time() . '.' . $ext;
    $file_path = $upload_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return 'uploads/' . $file_name;
    }

    return null;
}

try {
    // Validate email unique
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_result) > 0) throw new Exception("Email already exists");
    mysqli_stmt_close($check_stmt);

    // Validate phone unique
    $check_phone_sql = "SELECT id FROM users WHERE phone_number = ?";
    $check_phone_stmt = mysqli_prepare($conn, $check_phone_sql);
    mysqli_stmt_bind_param($check_phone_stmt, "s", $phone_number);
    mysqli_stmt_execute($check_phone_stmt);
    $check_phone_result = mysqli_stmt_get_result($check_phone_stmt);
    if (mysqli_num_rows($check_phone_result) > 0) throw new Exception("Phone number already exists");
    mysqli_stmt_close($check_phone_stmt);

    // Hash password (only if provided)
    $hashed_password = $password !== null ? password_hash($password, PASSWORD_DEFAULT) : null;

    // Insert user (with OAuth fields)
    $sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, google_id, linkedin_id, auth_provider, created_at, last_activity) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssissss", $user_name, $email, $hashed_password, $phone_number, $role, $is_verified, $status, $google_id, $linkedin_id, $auth_provider);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("User registration failed");
    $user_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $profile_created = false;
    $profile_id = null;

    switch ($role) {

        // ==================================================
        // RECRUITER PROFILE
        // ==================================================
        case 'recruiter':
            $company_name = $user_name;
            $gst_pan = $data['gst_pan'] ?? null;
            $company_logo = handleFileUpload('company_logo');
            $industry = $data['industry_type'] ?? null;
            $website = $data['company_website'] ?? null;
            $location = $data['office_address'] ?? null;

            $profile_sql = "INSERT INTO recruiter_profiles 
                (user_id, company_name, gst_pan, company_logo, industry, website, location, created_at, modified_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $profile_stmt = mysqli_prepare($conn, $profile_sql);
            mysqli_stmt_bind_param($profile_stmt, "issssss", $user_id, $company_name, $gst_pan, $company_logo, $industry, $website, $location);
            mysqli_stmt_execute($profile_stmt);

            $profile_id = mysqli_insert_id($conn);
            $profile_created = true;
            mysqli_stmt_close($profile_stmt);
            break;

        // ==================================================
        // INSTITUTE PROFILE (FIXED ERROR HERE)
        // ==================================================
        case 'institute':
            $institute_name = $user_name;
            $registration_number = $data['registration_number'] ?? null;
            $institute_type = $data['institute_type'] ?? 'Private';
            $website = $data['institute_website'] ?? null;
            $description = $data['description'] ?? null;
            $address = $data['institute_address'] ?? null;
            $postal_code = $data['postal_code'] ?? null;
            $contact_person = $data['principal_name'] ?? null;
            $contact_designation = "Principal";
            $accreditation = $data['affiliation_details'] ?? null;
            $established_year = isset($data['established_year']) ? (int)$data['established_year'] : null;
            $institute_logo = handleFileUpload('institute_logo');

            $profile_sql = "INSERT INTO institute_profiles 
                (user_id, institute_name, registration_number, institute_type, website, description, 
                 address, postal_code, contact_person, contact_designation, accreditation, established_year, created_at, modified_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $profile_stmt = mysqli_prepare($conn, $profile_sql);

            /** FIXED HERE (12 VARIABLES → 12 TYPES) */
            mysqli_stmt_bind_param(
                $profile_stmt,
                "issssssssssi",   // ← CORRECTED
                $user_id,
                $institute_name,
                $registration_number,
                $institute_type,
                $website,
                $description,
                $address,
                $postal_code,
                $contact_person,
                $contact_designation,
                $accreditation,
                $established_year
            );

            mysqli_stmt_execute($profile_stmt);
            $profile_id = mysqli_insert_id($conn);
            $profile_created = true;
            mysqli_stmt_close($profile_stmt);
            break;

        // ==================================================
        // STUDENT PROFILE
        // ==================================================
        case 'student':

            $skills = isset($data['skills']) ? (is_array($data['skills']) ? json_encode($data['skills']) : $data['skills']) : null;
            // ✅ Bio should come from input data, not automatically set to user_name
            $bio = isset($data['bio']) ? trim($data['bio']) : (isset($data['additional_info']['bio']) ? trim($data['additional_info']['bio']) : null);
            $resume = handleFileUpload('resume_cv');
            $socials = isset($data['linkedin_portfolio_link']) ? json_encode(['linkedin' => $data['linkedin_portfolio_link']]) : null;
            $dob = $data['date_of_birth'] ?? null;
            $gender = isset($data['gender']) ? strtolower($data['gender']) : null;
            $trade = $data['highest_qualification'] ?? null;
            $location = $data['preferred_job_location'] ?? null;
            $contact_email = $email;
            $contact_phone = $phone_number;
            $graduation_year = isset($data['passing_year']) ? (int)$data['passing_year'] : null;
            $cgpa = isset($data['marks_cgpa']) ? (float)$data['marks_cgpa'] : null;
            $profile_photo = handleFileUpload('profile_photo');

            $education = null;
            if (isset($data['highest_qualification'])) {
                $education = $data['highest_qualification'];
            }

            $profile_sql = "INSERT INTO student_profiles 
                (user_id, skills, bio, education, resume, certificates, socials, dob, gender, job_type, trade,
                 location, contact_email, contact_phone, experience, projects, languages, aadhar_number,
                 graduation_year, cgpa, latitude, longitude, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

            $profile_stmt = mysqli_prepare($conn, $profile_sql);
            mysqli_stmt_bind_param(
                $profile_stmt,
                "issssssssssssssssidddd",
                $user_id, $skills, $bio, $education, $resume, $certificates, $socials,
                $dob, $gender, $job_type, $trade, $location, $contact_email, $contact_phone,
                $experience, $projects, $languages, $aadhar_number,
                $graduation_year, $cgpa, $latitude, $longitude
            );

            mysqli_stmt_execute($profile_stmt);
            $profile_id = mysqli_insert_id($conn);
            $profile_created = true;
            mysqli_stmt_close($profile_stmt);
            break;
    }

    if (!$profile_created && $role !== 'admin') {
        throw new Exception("Failed to create $role profile");
    }

    mysqli_commit($conn);

    $response = [
        "message" => "User registered successfully",
        "status" => true,
        "user_id" => $user_id,
        "role" => $role,
        "email" => $email,
        "user_name" => $user_name,
        "profile_id" => $profile_id
    ];

    echo json_encode($response);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(["message" => $e->getMessage(), "status" => false]);
}

mysqli_autocommit($conn, TRUE);
mysqli_close($conn);
?>
