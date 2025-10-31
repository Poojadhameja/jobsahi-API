<?php
// save_job.php - Save job to student's bookmarks (JWT required)
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
        "status"  => false,
        "decoded" => $decoded
    ]);
    exit;
}
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
        "status"  => false,
        "decoded" => $decoded
    ]);
    exit;
}
// Get input data - Handle both JSON and form data
$input = null;
$job_id = null;

// Check Content-Type and get data accordingly
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

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
$check_student_sql = "SELECT id FROM student_profiles WHERE id = ?";
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
mysqli_stmt_close($check_student_stmt);

// ✅ Check if job exists
$check_job_sql = "SELECT id, title FROM jobs WHERE id = ? AND status = 'open'";
$check_job_stmt = mysqli_prepare($conn, $check_job_sql);
mysqli_stmt_bind_param($check_job_stmt, "i", $job_id);
mysqli_stmt_execute($check_job_stmt);
$job_result = mysqli_stmt_get_result($check_job_stmt);

if (mysqli_num_rows($job_result) === 0) {
    echo json_encode(["message" => "Job not found or inactive", "status" => false]);
    mysqli_stmt_close($check_job_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_job_stmt);

// ✅ Check if already saved
$check_saved_sql = "SELECT id FROM saved_jobs WHERE student_id = ? AND job_id = ?";
$check_saved_stmt = mysqli_prepare($conn, $check_saved_sql);
mysqli_stmt_bind_param($check_saved_stmt, "ii", $student_id, $job_id);
mysqli_stmt_execute($check_saved_stmt);
$saved_result = mysqli_stmt_get_result($check_saved_stmt);

if (mysqli_num_rows($saved_result) > 0) {
    echo json_encode([
        "message" => "Job is already saved to bookmarks", 
        "status" => false,
        "already_saved" => true
    ]);
    mysqli_stmt_close($check_saved_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_saved_stmt);

// ✅ Insert into saved_jobs
$insert_sql = "INSERT INTO saved_jobs (student_id, job_id, saved_at) VALUES (?, ?, NOW())";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($insert_stmt, "ii", $student_id, $job_id);

if (mysqli_stmt_execute($insert_stmt)) {
    $saved_job_id = mysqli_insert_id($conn);

    // Fetch saved details
    $get_saved_sql = "SELECT sj.id, sj.student_id, sj.job_id, sj.saved_at,
                             j.title, j.location, j.job_type, j.salary_min, j.salary_max
                      FROM saved_jobs sj
                      JOIN jobs j ON sj.job_id = j.id
                      WHERE sj.id = ?";
    $get_saved_stmt = mysqli_prepare($conn, $get_saved_sql);
    
    if ($get_saved_stmt) {
        mysqli_stmt_bind_param($get_saved_stmt, "i", $saved_job_id);
        mysqli_stmt_execute($get_saved_stmt);
        $saved_job_result = mysqli_stmt_get_result($get_saved_stmt);
        $saved_job_data = mysqli_fetch_assoc($saved_job_result);

        echo json_encode([
            "message" => "Job saved to bookmarks successfully",
            "status" => true,
            "data" => $saved_job_data,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        mysqli_stmt_close($get_saved_stmt);
    } else {
        echo json_encode([
            "message" => "Job saved successfully but couldn't fetch details",
            "status" => true,
            "saved_job_id" => $saved_job_id,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    }
} else {
    echo json_encode(["message" => "Failed to save job: " . mysqli_stmt_error($insert_stmt), "status" => false]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>
