<?php
// job_view.php - Record Job View API
require_once '../cors.php';

// ✅ Authenticate (student + admin)
$decoded = authenticateJWT(['student', 'admin','recruiter','institute']); // decoded JWT payload

// ✅ Get job_id ONLY from URL (?id=...)
$job_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$job_id) {
    echo json_encode(["message" => "Job ID missing in URL", "status" => false]);
    exit;
}

// ✅ Extract student_id from JWT
$student_id = isset($decoded['id']) ? (int)$decoded['id'] : (int)$decoded['user_id'];
$user_role  = $decoded['role'] ?? null;

if (empty($student_id)) {
    echo json_encode(["message" => "❌ JWT missing user ID", "status" => false]);
    exit;
}

// ---- JOB VISIBILITY RULE ----
if ($user_role === 'admin') {
    $check_job_sql = "SELECT id, title, status, admin_action FROM jobs WHERE id = ?";
} else {
    $check_job_sql = "SELECT id, title, status, admin_action 
                      FROM jobs 
                      WHERE id = ? AND admin_action = 'approved'";
}
// ✅ Authenticate (student + admin)
$decoded = authenticateJWT(['student', 'admin']); 


// ✅ Get job_id ONLY from URL (?id=...)
$job_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$job_id) {
    echo json_encode(["message" => "Job ID missing in URL", "status" => false]);
    exit;
}

// ✅ Extract student_id from JWT
$student_id = isset($decoded['id']) ? (int)$decoded['id'] : (int)$decoded['user_id'];
$user_role  = $decoded['role'] ?? null;

if (empty($student_id)) {
    echo json_encode(["message" => "❌ JWT missing user ID", "status" => false]);
    exit;
}

// ---- JOB VISIBILITY RULE ----
if ($user_role === 'admin') {
    $check_job_sql = "SELECT id, title, status, admin_action FROM jobs WHERE id = ?";
} else {
    $check_job_sql = "SELECT id, title, status, admin_action 
                      FROM jobs 
                      WHERE id = ? AND admin_action = 'approval'";
}

$check_stmt = mysqli_prepare($conn, $check_job_sql);
mysqli_stmt_bind_param($check_stmt, "i", $job_id);

if (!$check_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_execute($check_stmt);
$job_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($job_result) === 0) {
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    echo json_encode([
        "message" => "Job not available (role: $user_role)",
        "status" => false
    ]);
    exit;
}

$job_data = mysqli_fetch_assoc($job_result);

// ✅ Only active jobs allowed
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

// ---- USER CHECK ----
$check_student_sql = "SELECT id, user_name, email, role FROM users WHERE id = ?";
$student_stmt = mysqli_prepare($conn, $check_student_sql);
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
mysqli_stmt_close($student_stmt);

// ---- JOB VIEW LOGIC ----
$today = date('Y-m-d');
$check_view_sql = "SELECT id FROM job_views WHERE job_id = ? AND student_id = ? AND DATE(viewed_at) = ?";
$view_check_stmt = mysqli_prepare($conn, $check_view_sql);
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

// Insert new view
$current_datetime = date('Y-m-d H:i:s');
$insert_sql = "INSERT INTO job_views (job_id, student_id, viewed_at) VALUES (?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
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
            "student_name" => $student_data['user_name'],
            "role" => $student_data['role'],
            "status" => $job_data['status'],
            "admin_action" => $job_data['admin_action'],
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
