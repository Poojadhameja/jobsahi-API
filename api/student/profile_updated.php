<?php
require_once '../cors.php';
require_once '../db.php'; // ✅ DB connection

// ✅ Authenticate JWT (allowed roles: admin, student)
$current_user = authenticateJWT(['admin', 'student']);
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
} else {
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

// ✅ Debugging support (optional)
file_put_contents('debug_profile_update.txt', print_r($input, true));

// ✅ Extract all expected fields
$skills           = $input['skills'] ?? null;
$education        = $input['education'] ?? null;
$resume           = $input['resume'] ?? null;
$certificates     = $input['certificates'] ?? null;
$portfolio_link   = $input['portfolio_link'] ?? null;
$linkedin_url     = $input['linkedin_url'] ?? null;
$dob              = $input['dob'] ?? null;
$gender           = $input['gender'] ?? null;
$job_type         = $input['job_type'] ?? null;
$trade            = $input['trade'] ?? null;
$bio              = $input['bio'] ?? null;
$experience       = $input['experience'] ?? null;
$projects         = $input['projects'] ?? null;
$languages        = $input['languages'] ?? null;
$aadhar_number    = $input['aadhar_number'] ?? null;
$graduation_year  = $input['graduation_year'] ?? null;
$cgpa             = $input['cgpa'] ?? null;
$latitude         = $input['latitude'] ?? null;
$longitude        = $input['longitude'] ?? null;
$location         = $input['location'] ?? null;

// ✅ Convert experience to JSON if array
if (is_array($experience)) {
    $experience = json_encode($experience, JSON_UNESCAPED_UNICODE);
}

// ✅ Convert projects (name + link) to JSON if array
if (is_array($projects)) {
    $projects = json_encode($projects, JSON_UNESCAPED_UNICODE);
}

// ✅ Build UPDATE SQL query
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
            modified_at = NOW()
        WHERE id = ? AND deleted_at IS NULL";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode([
        "message" => "Failed to prepare statement: " . mysqli_error($conn),
        "status" => false
    ]);
    mysqli_close($conn);
    exit;
}

// ✅ Bind all parameters (21 total)
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

// ✅ Execute
if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            "message" => "Student profile updated successfully",
            "status" => true,
            "profile_updated_id" => $student_id,
            "updated_by" => $user_role,
            "timestamp" => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "message" => "No record updated. Check student_id or profile may be deleted",
            "status" => false
        ]);
    }
} else {
    echo json_encode([
        "message" => "Update failed: " . mysqli_stmt_error($stmt),
        "status" => false
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
