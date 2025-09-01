<?php
// job_view.php - Record Job View API

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Include JWT helper & middleware
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate (allow student + admin roles)
$decoded = authenticateJWT(['student', 'admin']); 
// Debug: log JWT payload
error_log("Decoded JWT: " . print_r($decoded, true));

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "❌ DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
} else {
    echo "✅ Database connected successfully";
}

// Get request body
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

$job_id = (int)$input['job_id'];

// ✅ Fix: use correct key from JWT
$student_id = isset($decoded['id']) ? (int)$decoded['id'] : (int)$decoded['user_id'];
$student_role = $decoded['role'] ?? null;

// If still empty, stop
if (empty($student_id)) {
    echo json_encode(["message" => "❌ JWT missing user ID", "status" => false]);
    exit;
}

// ---- rest of your code unchanged ----

// Check if job exists
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

// Check if user exists
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

// ✅ Role check (already handled by authenticateJWT, but double safety)
$allowed_roles = ['student', 'admin'];
if (!in_array($student_data['role'], $allowed_roles)) {
    mysqli_stmt_close($student_stmt);
    mysqli_close($conn);
    echo json_encode([
        "message" => "User is not allowed (role: " . $student_data['role'] . ")",
        "status" => false
    ]);
    exit;
}
mysqli_stmt_close($student_stmt);

// Check if this view already exists today
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
            "role" => $student_data['role'],
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
