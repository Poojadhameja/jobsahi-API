<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    echo json_encode([
        "message" => "Valid job ID is required. Pass it as URL parameter (?id=123) or in request body",
        "status" => false
    ]);
    exit;
}

// Validate required fields
$required_fields = ['student_id', 'cover_letter'];
$missing_fields = [];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        "message" => "Missing required fields: " . implode(', ', $missing_fields),
        "status" => false
    ]);
    exit;
}

// Check if job exists
$check_job_sql = "SELECT id, status, application_deadline FROM jobs WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_job_sql);
if (!$check_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}
mysqli_stmt_bind_param($check_stmt, "i", $job_id);
mysqli_stmt_execute($check_stmt);
$job_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($job_result) === 0) {
    echo json_encode(["message" => "Job not found", "status" => false]);
    exit;
}

$job = mysqli_fetch_assoc($job_result);
mysqli_stmt_close($check_stmt);

// Check if job is open (customize allowed statuses)
$allowed_statuses = ['active', 'open']; // define which statuses allow applications
if (!in_array(strtolower($job['status']), $allowed_statuses)) {
    echo json_encode(["message" => "This job is no longer accepting applications", "status" => false]);
    exit;
}

// Check application deadline
if (!empty($job['application_deadline']) && strtotime($job['application_deadline']) < time()) {
    echo json_encode(["message" => "Application deadline has passed", "status" => false]);
    exit;
}

// Check if already applied
$check_application_sql = "SELECT id FROM applications WHERE job_id = ? AND student_id = ?";
$check_app_stmt = mysqli_prepare($conn, $check_application_sql);
if (!$check_app_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}
mysqli_stmt_bind_param($check_app_stmt, "ii", $job_id, $input['student_id']);
mysqli_stmt_execute($check_app_stmt);
$app_result = mysqli_stmt_get_result($check_app_stmt);

if (mysqli_num_rows($app_result) > 0) {
    echo json_encode(["message" => "You have already applied for this job", "status" => false]);
    exit;
}
mysqli_stmt_close($check_app_stmt);

// Insert application
$insert_sql = "INSERT INTO applications (
    job_id,
    student_id,
    cover_letter,
    resume_link,
    status,
    applied_at
) VALUES (?, ?, ?, ?, 'pending', NOW())";

$insert_stmt = mysqli_prepare($conn, $insert_sql);
if (!$insert_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// Handle optional resume_link
$resume_link = isset($input['resume_link']) ? $input['resume_link'] : null;

mysqli_stmt_bind_param($insert_stmt, "iis",
    $job_id,
    $input['student_id'],
    $input['cover_letter'],
    // $resume_link
);

if (mysqli_stmt_execute($insert_stmt)) {
    $application_id = mysqli_insert_id($conn);

    // Fetch newly inserted application details
    $get_application_sql = "SELECT 
        a.id,
        a.job_id,
        a.student_id,
        a.cover_letter,
        -- a.resume_link,
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

    echo json_encode([
        "message" => "Application submitted successfully",
        "status" => true,
        "application_id" => $application_id,
        "data" => $application_data,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "message" => "Failed to submit application: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>
