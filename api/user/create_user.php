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
    // Handle multipart/form-data (for file uploads)
    $data = $_POST;
    // Files will be handled separately via $_FILES
} else {
    // Handle JSON data
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(array("message" => "Invalid JSON data", "status" => false));
        exit;
    }
}

// Check if required fields exist
$required_fields = ['user_name', 'email', 'password', 'phone_number', 'role'];
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
$role = isset($data['role']) ? trim($data['role']) : 'student';
$is_verified = isset($data['is_verified']) ? (int)$data['is_verified'] : 0;
$status = isset($data['status']) ? trim($data['status']) : 'active';

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

// Function to handle file upload
function handleFileUpload($file_key, $upload_dir = '../uploads/') {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $file = $_FILES[$file_key];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    
    if (!in_array($file['type'], $allowed_types)) {
        return null;
    }
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Return relative path
        return 'uploads/' . $file_name;
    }
    
    return null;
}

try {
    // Check if email already exists
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

    // Check if phone number already exists
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

    // Insert new user
    $sql = "INSERT INTO users (user_name, email, password, phone_number, role, is_verified, status, created_at, last_activity) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssssis", $user_name, $email, $hashed_password, $phone_number, $role, $is_verified, $status);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("User registration failed: " . mysqli_error($conn));
        }
        
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Create profile based on role with all fields - using actual table column names
        $profile_created = false;
        $profile_id = null;
        
        switch ($role) {
            case 'admin':
                // Admin profile (if you have admin_profiles table)
                $employee_id = isset($data['employee_id']) ? trim($data['employee_id']) : null;
                $profile_photo = handleFileUpload('profile_photo');
                
                // Check if admin_profiles table exists, if not skip
                $profile_created = true; // Admin doesn't require separate profile
                break;
                
            case 'recruiter':
                // recruiter_profiles table columns: id, user_id, company_name, gst_pan, company_logo, industry, website, location, created_at, modified_at, deleted_at, admin_action
                $company_name = isset($data['user_name']) ? trim($data['user_name']) : null; // user_name is company_name for recruiter
                $gst_pan = isset($data['gst_pan']) ? trim($data['gst_pan']) : null;
                $company_logo = handleFileUpload('company_logo');
                $industry = isset($data['industry_type']) ? trim($data['industry_type']) : null;
                $website = isset($data['company_website']) ? trim($data['company_website']) : null;
                $location = isset($data['office_address']) ? trim($data['office_address']) : null;
                $admin_action = 'pending'; // Default pending for new recruiters
                
                $profile_sql = "INSERT INTO recruiter_profiles 
                                (user_id, company_name, gst_pan, company_logo, industry, website, location, admin_action, created_at, modified_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                    mysqli_stmt_bind_param($profile_stmt, "isssssss", $user_id, $company_name, $gst_pan, $company_logo, $industry, $website, $location, $admin_action);
                    if (mysqli_stmt_execute($profile_stmt)) {
                        $profile_id = mysqli_insert_id($conn);
                        $profile_created = true;
                    }
                    mysqli_stmt_close($profile_stmt);
                }
                break;
                
            case 'institute':
                // institute_profiles table columns: id, user_id, institute_name, registration_number, institute_type, website, description, address, postal_code, contact_person, contact_designation, accreditation, established_year, created_at, modified_at, deleted_at, admin_action
                $institute_name = isset($data['user_name']) ? trim($data['user_name']) : null; // user_name is institute_name for institute
                $registration_number = isset($data['registration_number']) ? trim($data['registration_number']) : null;
                $institute_type = isset($data['institute_type']) ? trim($data['institute_type']) : 'Private'; // Default Private
                $website = isset($data['institute_website']) ? trim($data['institute_website']) : null;
                $description = isset($data['description']) ? trim($data['description']) : null; // Fixed: use 'description' not 'instituteDescription'
                $address = isset($data['institute_address']) ? trim($data['institute_address']) : null;
                $postal_code = isset($data['postal_code']) ? trim($data['postal_code']) : null; // Fixed: use 'postal_code' not 'postalCode'
                $contact_person = isset($data['principal_name']) ? trim($data['principal_name']) : null;
                $contact_designation = 'Principal'; // Default
                $accreditation = isset($data['affiliation_details']) ? trim($data['affiliation_details']) : null;
                $established_year = isset($data['established_year']) ? (int)trim($data['established_year']) : null; // Fixed: use 'established_year' not 'establishedYear', also convert to int
                $institute_logo = handleFileUpload('institute_logo');
                $admin_action = 'pending'; // Default pending for new institutes
                
                // Debug: Log all received data
                error_log("Institute Data Received: " . print_r($data, true));
                error_log("Description: " . ($description ?? 'NULL'));
                error_log("Postal Code: " . ($postal_code ?? 'NULL'));
                error_log("Established Year: " . ($established_year ?? 'NULL'));
                
                $profile_sql = "INSERT INTO institute_profiles 
                                (user_id, institute_name, registration_number, institute_type, website, description, 
                                 address, postal_code, contact_person, contact_designation, accreditation, established_year, admin_action, created_at, modified_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                    mysqli_stmt_bind_param($profile_stmt, "issssssssssis", $user_id, $institute_name, $registration_number, $institute_type, 
                                          $website, $description, $address, $postal_code, $contact_person, $contact_designation, 
                                          $accreditation, $established_year, $admin_action);
                    if (mysqli_stmt_execute($profile_stmt)) {
                        $profile_id = mysqli_insert_id($conn);
                        $profile_created = true;
                    } else {
                        error_log("Institute Profile Insert Error: " . mysqli_error($conn));
                        throw new Exception("Failed to insert institute profile: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($profile_stmt);
                } else {
                    error_log("Institute Profile Prepare Error: " . mysqli_error($conn));
                    throw new Exception("Failed to prepare institute profile statement: " . mysqli_error($conn));
                }
                break;
                
            case 'student':
                // student_profiles table columns: id, user_id, skills, bio, education, resume, certificates, socials, dob, gender, job_type, trade, location, contact_email, contact_phone, experience, projects, languages, aadhar_number, graduation_year, cgpa, latitude, longitude, created_at, updated_at, deleted_at
                $skills = isset($data['skills']) ? (is_array($data['skills']) ? json_encode($data['skills']) : $data['skills']) : null;
                $bio = isset($data['user_name']) ? trim($data['user_name']) : null; // Using user_name as bio initially
                $education = null; // Will construct from form data
                $resume = handleFileUpload('resume_cv');
                $certificates = null;
                $socials = isset($data['linkedin_portfolio_link']) ? json_encode(array('linkedin' => $data['linkedin_portfolio_link'])) : null;
                $dob = isset($data['date_of_birth']) ? trim($data['date_of_birth']) : null;
                $gender = isset($data['gender']) ? strtolower(trim($data['gender'])) : null; // Convert to lowercase for enum
                $job_type = null; // Not in form
                $trade = isset($data['highest_qualification']) ? trim($data['highest_qualification']) : null;
                $location = isset($data['preferred_job_location']) ? trim($data['preferred_job_location']) : 
                           (isset($data['city']) && isset($data['state']) ? trim($data['city']) . ', ' . trim($data['state']) : null);
                $contact_email = isset($data['email']) ? trim($data['email']) : null;
                $contact_phone = isset($data['phone_number']) ? trim($data['phone_number']) : null;
                $experience = null; // Not in form
                $projects = null; // Not in form
                $languages = null; // Not in form
                $aadhar_number = null; // Not in form
                $graduation_year = isset($data['passing_year']) ? (int)$data['passing_year'] : null;
                $cgpa = isset($data['marks_cgpa']) ? (float)$data['marks_cgpa'] : null;
                $latitude = null; // Not in form
                $longitude = null; // Not in form
                $profile_photo = handleFileUpload('profile_photo');
                
                // Construct education string
                if (isset($data['highest_qualification']) || isset($data['college_name']) || isset($data['passing_year']) || isset($data['marks_cgpa'])) {
                    $education_parts = array();
                    if (isset($data['highest_qualification'])) $education_parts[] = trim($data['highest_qualification']);
                    if (isset($data['college_name'])) $education_parts[] = 'from ' . trim($data['college_name']);
                    if (isset($data['passing_year'])) $education_parts[] = 'Year: ' . trim($data['passing_year']);
                    if (isset($data['marks_cgpa'])) $education_parts[] = 'CGPA: ' . trim($data['marks_cgpa']);
                    $education = implode(', ', $education_parts);
                }
                
                $profile_sql = "INSERT INTO student_profiles 
                                (user_id, skills, bio, education, resume, certificates, socials, dob, gender, job_type, trade, 
                                 location, contact_email, contact_phone, experience, projects, languages, aadhar_number, 
                                 graduation_year, cgpa, latitude, longitude, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                if ($profile_stmt = mysqli_prepare($conn, $profile_sql)) {
                    mysqli_stmt_bind_param($profile_stmt, "issssssssssssssssidddd", $user_id, $skills, $bio, $education, $resume, 
                                          $certificates, $socials, $dob, $gender, $job_type, $trade, $location, $contact_email, 
                                          $contact_phone, $experience, $projects, $languages, $aadhar_number, $graduation_year, 
                                          $cgpa, $latitude, $longitude);
                    if (mysqli_stmt_execute($profile_stmt)) {
                        $profile_id = mysqli_insert_id($conn);
                        $profile_created = true;
                    }
                    mysqli_stmt_close($profile_stmt);
                }
                break;
        }
        
        if (!$profile_created && $role !== 'admin') {
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
            "is_verified" => $is_verified
        );
        
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

