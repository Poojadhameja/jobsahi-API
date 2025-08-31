<?php
// apply_job.php - Apply for a Job (Student only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate and allow only "student" role
$decoded = authenticateJWT('student');  // decoded JWT payload

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

include "../db.php"; 

if (!$conn) {
    http_response_code(500);
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// Read POST JSON
$input = json_decode(file_get_contents('php://input'), true);

// Get job_id from URL (?id=) or from request body
$job_id = 0;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $job_id = intval($_GET['id']);
} elseif (isset($input['job_id']) && !empty($input['job_id'])) {
    $job_id = intval($input['job_id']);
}

if ($job_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "message" => "Valid job ID is required. Pass it as URL parameter (?id=123) or in request body",
        "status" => false
    ]);
    exit;
}

// ✅ Student ID from JWT payload
$student_id = $decoded['id'] ?? $decoded['user_id'] ?? $decoded['student_id'] ?? null;

if (!$student_id) {
    http_response_code(401);
    echo json_encode([
        "message" => "Invalid token: student ID missing",
        "status" => false
    ]);
    exit;
}

// Validate required fields
if (empty($input['cover_letter'])) {
    http_response_code(400);
    echo json_encode([
        "message" => "Missing required field: cover_letter",
        "status" => false
    ]);
    exit;
}

// ✅ Check if job exists
$check_job_sql = "SELECT id, status, application_deadline FROM jobs WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_job_sql);
mysqli_stmt_bind_param($check_stmt, "i", $job_id);
mysqli_stmt_execute($check_stmt);
$job_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($job_result) === 0) {
    http_response_code(404);
    echo json_encode(["message" => "Job not found", "status" => false]);
    exit;
}

$job = mysqli_fetch_assoc($job_result);
mysqli_stmt_close($check_stmt);

// Check if job is open
$allowed_statuses = ['active', 'open'];
if (!in_array(strtolower($job['status']), $allowed_statuses)) {
    echo json_encode(["message" => "This job is no longer accepting applications", "status" => false]);
    exit;
}

// Check deadline
if (!empty($job['application_deadline']) && strtotime($job['application_deadline']) < time()) {
    echo json_encode(["message" => "Application deadline has passed", "status" => false]);
    exit;
}

// ✅ Check if already applied
$check_application_sql = "SELECT id FROM applications WHERE job_id = ? AND student_id = ?";
$check_app_stmt = mysqli_prepare($conn, $check_application_sql);
mysqli_stmt_bind_param($check_app_stmt, "ii", $job_id, $student_id);
mysqli_stmt_execute($check_app_stmt);
$app_result = mysqli_stmt_get_result($check_app_stmt);

if (mysqli_num_rows($app_result) > 0) {
    echo json_encode(["message" => "You have already applied for this job", "status" => false]);
    exit;
}
mysqli_stmt_close($check_app_stmt);

// ✅ Insert application
$insert_sql = "INSERT INTO applications (
    job_id,
    student_id,
    cover_letter,
    resume_link,
    status,
    applied_at
) VALUES (?, ?, ?, ?, 'pending', NOW())";

$insert_stmt = mysqli_prepare($conn, $insert_sql);

$resume_link = isset($input['resume_link']) && !empty($input['resume_link']) ? $input['resume_link'] : "";

mysqli_stmt_bind_param(
    $insert_stmt,
    "iiss",
    $job_id,
    $student_id,
    $input['cover_letter'],
    $resume_link
);

if (mysqli_stmt_execute($insert_stmt)) {
    $application_id = mysqli_insert_id($conn);

    // Fetch newly inserted application
    $get_application_sql = "SELECT 
        a.id,
        a.job_id,
        a.student_id,
        a.cover_letter,
        a.resume_link,
        a.status,
        a.applied_at,
        j.title as job_title,
        j.recruiter_id
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.id = ?";
    
    $get_stmt = mysqli_prepare($conn, $get_application_sql);
    mysqli_stmt_bind_param($get_stmt, "i", $application_id);
    mysqli_stmt_execute($get_stmt);
    $result = mysqli_stmt_get_result($get_stmt);
    $application_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($get_stmt);

    http_response_code(201);
    echo json_encode([
        "message" => "Application submitted successfully",
        "status" => true,
        "application_id" => $application_id,
        "data" => $application_data,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "message" => "Failed to submit application: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>
