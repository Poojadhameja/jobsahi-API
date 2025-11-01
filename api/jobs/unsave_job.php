<?php
// unsave_job.php - Remove saved job for student (JWT required)
require_once '../cors.php';

// ✅ Authenticate JWT (only student can access)
$decoded = authenticateJWT('student'); 

// Handle different key names from JWT payload safely
$student_id = null;
if (isset($decoded['id'])) {
    $student_id = $decoded['id'];
} elseif (isset($decoded['user_id'])) {
    $student_id = $decoded['user_id'];
} elseif (isset($decoded['student_id'])) {
    $student_id = $decoded['student_id'];
}

if (!$student_id) {
    echo json_encode([
        "message" => "Invalid token payload: student id missing",
        "status"  => false
    ]);
    exit;
}

// Get input data - Handle both JSON and form data
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
$job_id = null;

if (strpos($content_type, "application/json") !== false) {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["message" => "Invalid JSON format: " . json_last_error_msg(), "status" => false]);
        exit;
    }
    
    $job_id = isset($input['job_id']) ? intval($input['job_id']) : null;
} else {
    $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : null;
}

// Validation
if (!$job_id || $job_id <= 0) {
    echo json_encode([
        "message" => "Job ID is required and must be a positive integer",
        "status" => false,
        "received_job_id" => $job_id
    ]);
    exit;
}

// ✅ Check if student exists in student_profiles table
$check_student_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
$check_student_stmt = mysqli_prepare($conn, $check_student_sql);
mysqli_stmt_bind_param($check_student_stmt, "i", $student_id);
mysqli_stmt_execute($check_student_stmt);
$student_result = mysqli_stmt_get_result($check_student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    echo json_encode([
        "message" => "Student profile not found. Please complete your profile.", 
        "status" => false,
        "student_id" => $student_id
    ]);
    mysqli_stmt_close($check_student_stmt);
    mysqli_close($conn);
    exit;
}

// Get the actual student profile ID
$student_profile = mysqli_fetch_assoc($student_result);
$student_profile_id = $student_profile['id'];
mysqli_stmt_close($check_student_stmt);

// ✅ Check if job is saved by this student and get job title
$check_sql = "SELECT sj.id, j.title FROM saved_jobs sj 
              INNER JOIN jobs j ON sj.job_id = j.id 
              WHERE sj.student_id = ? AND sj.job_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $student_profile_id, $job_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode([
        "message" => "Job is not saved by you or doesn't exist",
        "status" => false
    ]);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit;
}

$job_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($check_stmt);

// ✅ Delete from saved_jobs table
$delete_sql = "DELETE FROM saved_jobs WHERE student_id = ? AND job_id = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "ii", $student_profile_id, $job_id);

if (mysqli_stmt_execute($delete_stmt)) {
    echo json_encode([
        "message" => "Job removed from saved list successfully",
        "status" => true,
        "data" => [
            "job_id" => $job_id,
            "job_title" => $job_data['title'],
            "student_id" => $student_profile_id
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(["message" => "Failed to remove job: " . mysqli_stmt_error($delete_stmt), "status" => false]);
}

mysqli_stmt_close($delete_stmt);
mysqli_close($conn);
?>
