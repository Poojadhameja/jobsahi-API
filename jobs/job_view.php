<?php
// job_view.php - Record Job View API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

include "../config.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// Get request body for both job_id and student_id
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON decode was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["message" => "Invalid JSON input", "status" => false]);
    exit;
}

// Validate job_id
if (!isset($input['job_id']) || !is_numeric($input['job_id'])) {
    echo json_encode(["message" => "job_id is required and must be numeric", "status" => false]);
    exit;
}

// Validate student_id
if (!isset($input['student_id']) || !is_numeric($input['student_id'])) {
    echo json_encode(["message" => "student_id is required and must be numeric", "status" => false]);
    exit;
}

$job_id = (int)$input['job_id'];
$student_id = (int)$input['student_id'];

// Check if job exists (with more detailed checking)
$check_job_sql = "SELECT id, title, status FROM jobs WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_job_sql);

if (!$check_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($check_stmt, "i", $job_id);
mysqli_stmt_execute($check_stmt);
$job_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($job_result) === 0) {
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    echo json_encode(["message" => "Job with ID $job_id not found", "status" => false]);
    exit;
}

$job_data = mysqli_fetch_assoc($job_result);
if ($job_data['status'] !== 'open') {
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    echo json_encode([
        "message" => "Job is not active (status: " . $job_data['status'] . ")", 
        "status" => false
    ]);
    exit;
}

mysqli_stmt_close($check_stmt);

// Check if student exists (with more detailed checking)
$check_student_sql = "SELECT id, name, email, role FROM users WHERE id = ?";
$student_stmt = mysqli_prepare($conn, $check_student_sql);

if (!$student_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    mysqli_stmt_close($student_stmt);
    mysqli_close($conn);
    echo json_encode(["message" => "User with ID $student_id not found", "status" => false]);
    exit;
}

$student_data = mysqli_fetch_assoc($student_result);
if ($student_data['role'] !== 'student') {
    mysqli_stmt_close($student_stmt);
    mysqli_close($conn);
    echo json_encode([
        "message" => "User is not a student (role: " . $student_data['role'] . ")", 
        "status" => false
    ]);
    exit;
}

mysqli_stmt_close($student_stmt);

// Check if this view already exists today (to prevent duplicate views from same user on same day)
$today = date('Y-m-d');
$check_view_sql = "SELECT id FROM job_views WHERE job_id = ? AND student_id = ? AND DATE(viewed_at) = ?";
$view_check_stmt = mysqli_prepare($conn, $check_view_sql);

if (!$view_check_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($view_check_stmt, "iis", $job_id, $student_id, $today);
mysqli_stmt_execute($view_check_stmt);
$existing_view = mysqli_stmt_get_result($view_check_stmt);

if (mysqli_num_rows($existing_view) > 0) {
    mysqli_stmt_close($view_check_stmt);
    mysqli_close($conn);
    echo json_encode([
        "message" => "Job view already recorded for today",
        "status" => true,
        "data" => [
            "job_id" => $job_id,
            "student_id" => $student_id,
            "already_viewed_today" => true
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    exit;
}

mysqli_stmt_close($view_check_stmt);

// Insert new job view
$current_datetime = date('Y-m-d H:i:s');
$insert_sql = "INSERT INTO job_views (job_id, student_id, viewed_at) VALUES (?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_sql);

if (!$insert_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($insert_stmt, "iis", $job_id, $student_id, $current_datetime);

if (mysqli_stmt_execute($insert_stmt)) {
    $view_id = mysqli_insert_id($conn);
    
    mysqli_stmt_close($insert_stmt);
    mysqli_close($conn);
    
    echo json_encode([
        "message" => "Job view recorded successfully",
        "status" => true,
        "data" => [
            "view_id" => $view_id,
            "job_id" => $job_id,
            "student_id" => $student_id,
            "job_title" => $job_data['title'],
            "student_name" => $student_data['name'],
            "viewed_at" => $current_datetime
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    mysqli_stmt_close($insert_stmt);
    mysqli_close($conn);
    
    echo json_encode([
        "message" => "Failed to record job view: " . mysqli_error($conn),
        "status" => false
    ]);
}
?>