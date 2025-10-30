<?php
// remove_saved_job.php
require_once '../cors.php';

// ---- JWT Authentication ----

// ✅ Authenticate student only
$decoded = authenticateJWT('student');

// --- Fix: Extract student_id safely ---
// Common cases: "id", "student_id", "user_id", or nested inside "data"
if (isset($decoded['id'])) {
    $student_id = $decoded['id'];
} elseif (isset($decoded['student_id'])) {
    $student_id = $decoded['student_id'];
} elseif (isset($decoded['user_id'])) {
    $student_id = $decoded['user_id'];
} elseif (isset($decoded['data']['id'])) {
    $student_id = $decoded['data']['id'];
} else {
    echo json_encode(["message" => "Student ID not found in token payload", "status" => false, "decoded" => $decoded]);
    exit;
}
// ✅ Authenticate student only
$decoded = authenticateJWT('student');

// --- Fix: Extract student_id safely ---
// Common cases: "id", "student_id", "user_id", or nested inside "data"
if (isset($decoded['id'])) {
    $student_id = $decoded['id'];
} elseif (isset($decoded['student_id'])) {
    $student_id = $decoded['student_id'];
} elseif (isset($decoded['user_id'])) {
    $student_id = $decoded['user_id'];
} elseif (isset($decoded['data']['id'])) {
    $student_id = $decoded['data']['id'];
} else {
    echo json_encode(["message" => "Student ID not found in token payload", "status" => false, "decoded" => $decoded]);
    exit;
}
// --- Get input data ---
$job_id = null;
$input = [];

$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
$raw_input = file_get_contents('php://input');

// Handle JSON or x-www-form-urlencoded
if (strpos($content_type, "application/json") !== false && !empty($raw_input)) {
    $input = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["message" => "Invalid JSON format: " . json_last_error_msg(), "status" => false]);
        exit;
    }
} else {
    // Parse query string or form-data fallback
    parse_str($raw_input, $input);
}

// Support job_id via query string `/remove_saved_job.php?job_id=...`
if (isset($_GET['job_id'])) $input['job_id'] = $_GET['job_id'];

$job_id = isset($input['job_id']) ? intval($input['job_id']) : null;

// --- Validation ---
if (!$job_id || $job_id <= 0) {
    echo json_encode([
        "message" => "Job ID is required and must be a positive integer",
        "status" => false,
        "received_job_id" => $job_id
    ]);
    exit;
}

// --- Check if saved job exists ---
$check_sql = "SELECT id FROM saved_jobs WHERE student_id = ? AND job_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);

if (!$check_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($check_stmt, "ii", $student_id, $job_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode([
        "message" => "Job is not saved/bookmarked for this user",
        "status" => false
    ]);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_stmt);

// --- Delete the saved job ---
$delete_sql = "DELETE FROM saved_jobs WHERE student_id = ? AND job_id = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);

if (!$delete_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($delete_stmt, "ii", $student_id, $job_id);

if (mysqli_stmt_execute($delete_stmt)) {
    echo json_encode([
        "message" => "Job removed from bookmarks successfully",
        "status" => true,
        "deleted_job_id" => $job_id,
        "student_id" => $student_id,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(["message" => "Failed to remove job: " . mysqli_stmt_error($delete_stmt), "status" => false]);
}

mysqli_stmt_close($delete_stmt);
mysqli_close($conn);
?>
