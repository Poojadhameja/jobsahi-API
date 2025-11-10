<?php
require_once '../cors.php';
require_once '../db.php'; // ✅ DB connection

// ✅ Authenticate JWT (allowed roles: admin, student, institute, recruiter)
$current_user = authenticateJWT(['admin', 'student', 'institute', 'recruiter']);
$user_role = strtolower($current_user['role']);
$user_id = $current_user['user_id'];

// ✅ Allow only PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["message" => "Only PUT requests allowed", "status" => false]);
    exit;
}

// ✅ Determine student_id based on role
if ($user_role === 'admin') {
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    if ($student_id <= 0) {
        echo json_encode(["message" => "Missing or invalid student_id for admin", "status" => false]);
        exit;
    }
} 
// ✅ NEW: For recruiter or institute, allow only approved student profiles
elseif (in_array($user_role, ['recruiter', 'institute'])) {
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

    if ($student_id <= 0) {
        echo json_encode(["message" => "Missing or invalid student_id for recruiter/institute", "status" => false]);
        exit;
    }

    // ✅ Check if student profile exists and approved
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE id = ? AND admin_action = 'approved' AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($approved_student_id);
    $stmt->fetch();
    $stmt->close();

    if (!$approved_student_id) {
        echo json_encode(["message" => "Student profile not found or not approved", "status" => false]);
        exit;
    }
}
// ✅ For student role
else {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($student_id);
    $stmt->fetch();
    $stmt->close();

    if (!$student_id) {
        echo json_encode(["message" => "Profile not found for this student", "status" => false]);
        exit;
    }
}

// ✅ Get JSON input
$input = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["message" => "Invalid JSON input", "status" => false]);
    exit;
}

// ✅ Extract all expected fields (support both flat and structured JSON)
$skills           = $input['skills'] ?? $input['professional_info']['skills'] ?? null;
$education        = $input['education'] ?? $input['professional_info']['education'] ?? null;
$resume           = $input['resume'] ?? $input['documents']['resume'] ?? null;
$certificates     = $input['certificates'] ?? $input['documents']['certificates'] ?? null;
$portfolio_link   = $input['portfolio_link'] ?? $input['social_links']['portfolio_link'] ?? null;
$linkedin_url     = $input['linkedin_url'] ?? $input['social_links']['linkedin_url'] ?? null;
$dob              = $input['dob'] ?? $input['personal_info']['date_of_birth'] ?? null;
$gender           = $input['gender'] ?? $input['personal_info']['gender'] ?? null;
$job_type         = $input['job_type'] ?? $input['professional_info']['job_type'] ?? null;
$trade            = $input['trade'] ?? $input['professional_info']['trade'] ?? null;
$bio              = $input['bio'] ?? $input['additional_info']['bio'] ?? null;
$experience       = $input['experience'] ?? $input['professional_info']['experience'] ?? null;
$projects         = $input['projects'] ?? $input['professional_info']['projects'] ?? null;
$languages        = $input['languages'] ?? $input['professional_info']['languages'] ?? null;
$aadhar_number    = $input['aadhar_number'] ?? $input['documents']['aadhar_number'] ?? null;
$graduation_year  = $input['graduation_year'] ?? $input['professional_info']['graduation_year'] ?? null;
$cgpa             = $input['cgpa'] ?? $input['professional_info']['cgpa'] ?? null;
$latitude         = $input['latitude'] ?? $input['personal_info']['latitude'] ?? null;
$longitude        = $input['longitude'] ?? $input['personal_info']['longitude'] ?? null;
$location         = $input['location'] ?? $input['personal_info']['location'] ?? null;
$email            = $input['email'] ?? $input['personal_info']['email'] ?? null;
$user_name        = $input['user_name'] ?? $input['personal_info']['user_name'] ?? null;
$phone_number     = $input['phone_number'] ?? $input['personal_info']['phone_number'] ?? null;

// ✅ Convert experience and projects to JSON
if (is_array($experience)) $experience = json_encode($experience, JSON_UNESCAPED_UNICODE);
if (is_array($projects)) $projects = json_encode($projects, JSON_UNESCAPED_UNICODE);

// ✅ Start transaction
mysqli_autocommit($conn, false);

$update_success = true;
$error_message = "";

// ✅ Update student_profiles table
$sql = "UPDATE student_profiles SET 
            skills = ?, 
            education = ?, 
            resume = ?, 
            certificates = ?, 
            portfolio_link = ?, 
            linkedin_url = ?, 
            dob = ?, 
            gender = ?, 
            job_type = ?, 
            trade = ?, 
            bio = ?, 
            experience = ?, 
            projects = ?, 
            languages = ?, 
            aadhar_number = ?, 
            graduation_year = ?, 
            cgpa = ?, 
            latitude = ?, 
            longitude = ?, 
            location = ?, 
            updated_at = NOW()
        WHERE id = ? AND deleted_at IS NULL";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $update_success = false;
    $error_message = "Failed to prepare student profile statement: " . mysqli_error($conn);
} else {
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssssssssssssddssi",
        $skills,
        $education,
        $resume,
        $certificates,
        $portfolio_link,
        $linkedin_url,
        $dob,
        $gender,
        $job_type,
        $trade,
        $bio,
        $experience,
        $projects,
        $languages,
        $aadhar_number,
        $graduation_year,
        $cgpa,
        $latitude,
        $longitude,
        $location,
        $student_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        $update_success = false;
        $error_message = "Student profile update failed: " . mysqli_stmt_error($stmt);
    }
    mysqli_stmt_close($stmt);
}

// ✅ Update users table (email/user_name/phone)
if ($update_success && ($email !== null || $user_name !== null || $phone_number !== null)) {
    $user_id_query = "SELECT user_id FROM student_profiles WHERE id = ? AND deleted_at IS NULL";
    $user_stmt = mysqli_prepare($conn, $user_id_query);
    if (!$user_stmt) {
        $update_success = false;
        $error_message = "Failed to prepare user_id query: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($user_stmt, "i", $student_id);
        mysqli_stmt_execute($user_stmt);
        mysqli_stmt_bind_result($user_stmt, $user_id_from_profile);
        mysqli_stmt_fetch($user_stmt);
        mysqli_stmt_close($user_stmt);

        if ($user_id_from_profile) {
            $user_update_fields = [];
            $user_params = [];
            $user_types = "";

            if ($email !== null) { $user_update_fields[] = "email = ?"; $user_params[] = $email; $user_types .= "s"; }
            if ($user_name !== null) { $user_update_fields[] = "user_name = ?"; $user_params[] = $user_name; $user_types .= "s"; }
            if ($phone_number !== null) { $user_update_fields[] = "phone_number = ?"; $user_params[] = $phone_number; $user_types .= "s"; }

            if (!empty($user_update_fields)) {
                $user_sql = "UPDATE users SET " . implode(", ", $user_update_fields) . " WHERE id = ?";
                $user_types .= "i";
                $user_params[] = $user_id_from_profile;

                $user_update_stmt = mysqli_prepare($conn, $user_sql);
                if (!$user_update_stmt) {
                    $update_success = false;
                    $error_message = "Failed to prepare user update statement: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($user_update_stmt, $user_types, ...$user_params);
                    if (!mysqli_stmt_execute($user_update_stmt)) {
                        $update_success = false;
                        $error_message = "User update failed: " . mysqli_stmt_error($user_update_stmt);
                    }
                    mysqli_stmt_close($user_update_stmt);
                }
            }
        }
    }
}

// ✅ Commit or rollback transaction
if ($update_success) {
    mysqli_commit($conn);
    echo json_encode([
        "success" => true,
        "message" => "Student profile updated successfully",
        "data" => [
            "profile_updated_id" => $student_id,
            "profile_updated_by_id" => $user_id, // ✅ NEW
            "profile_updated" => true,           // ✅ NEW
            "updated_by" => $user_role,
            "updated_fields" => [
                "student_profile" => true,
                "user_info" => ($email !== null || $user_name !== null || $phone_number !== null)
            ]
        ],
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0"
        ]
    ], JSON_PRETTY_PRINT);
} else {
    mysqli_rollback($conn);
    echo json_encode([
        "success" => false,
        "message" => "Update failed: " . $error_message,
        "data" => [
            "profile_updated" => false,
            "profile_updated_by_id" => $user_id, // ✅ NEW
            "error_details" => $error_message,
            "profile_id" => $student_id
        ],
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0"
        ]
    ], JSON_PRETTY_PRINT);
}

// ✅ Restore autocommit
mysqli_autocommit($conn, true);

mysqli_close($conn);
?>
