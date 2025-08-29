<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

include "../config.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// Get input data - Handle both JSON and form data
$input = null;
$job_id = null;
$student_id = null;

// Check Content-Type and get data accordingly
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if (strpos($content_type, "application/json") !== false) {
    // Handle JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    // Debug: Log the received data
    error_log("Raw input: " . $raw_input);
    error_log("Decoded input: " . print_r($input, true));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["message" => "Invalid JSON format: " . json_last_error_msg(), "status" => false]);
        exit;
    }
    
    $job_id = isset($input['job_id']) ? intval($input['job_id']) : null;
    $student_id= isset($input['student_id']) ? intval($input['student_id']) : null;
} else {
    // Handle form data (POST parameters)
    $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : null;
    $student_id= isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
}

// Validation
if (!$job_id || $job_id <= 0) {
    echo json_encode([
        "message" => "Job ID is required and must be a positive integer", 
        "status" => false,
        "received_job_id" => $job_id,
        "debug_info" => [
            "content_type" => $content_type,
            "post_data" => $_POST,
            "input_data" => $input
        ]
    ]);
    exit;
}

if (!$student_id|| $student_id<= 0) {
    echo json_encode([
        "message" => "User ID is required and must be a positive integer", 
        "status" => false,
        "received_user_id" => $student_id,
        "debug_info" => [
            "content_type" => $content_type,
            "post_data" => $_POST,
            "input_data" => $input
        ]
    ]);
    exit;
}

// ✅ Check if student exists in student_profiles table
$check_student_sql = "SELECT id FROM student_profiles WHERE id = ?";
$check_student_stmt = mysqli_prepare($conn, $check_student_sql);

if (!$check_student_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($check_student_stmt, "i", $student_id);
mysqli_stmt_execute($check_student_stmt);
$student_result = mysqli_stmt_get_result($check_student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    echo json_encode([
        "message" => "Student not found. Please make sure the student profile exists.", 
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

if (!$check_job_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

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

// ✅ Check if already saved (using correct column names: student_id and job_id)
$check_saved_sql = "SELECT id FROM saved_jobs WHERE student_id = ? AND job_id = ?";
$check_saved_stmt = mysqli_prepare($conn, $check_saved_sql);

if (!$check_saved_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

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

// ✅ Insert into saved_jobs (using correct column names: student_id and job_id)
$insert_sql = "INSERT INTO saved_jobs (student_id, job_id, saved_at) VALUES (?, ?, NOW())";
$insert_stmt = mysqli_prepare($conn, $insert_sql);

if (!$insert_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($insert_stmt, "ii", $student_id, $job_id);

if (mysqli_stmt_execute($insert_stmt)) {
    $saved_job_id = mysqli_insert_id($conn);

    // Fetch saved details (using correct column names: student_id and job_id)
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