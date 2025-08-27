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

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

$job_id = isset($input['job_id']) ? intval($input['job_id']) : null;
$user_id = isset($input['user_id']) ? intval($input['user_id']) : null;

if (!$job_id) {
    echo json_encode(["message" => "Job ID is required", "status" => false]);
    exit;
}
if (!$user_id) {
    echo json_encode(["message" => "User ID is required", "status" => false]);
    exit;
}

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

// ✅ Use actual column names (your table has `userid` and `jobid`)
$check_saved_sql = "SELECT id FROM saved_jobs WHERE userid = ? AND jobid = ?";
$check_saved_stmt = mysqli_prepare($conn, $check_saved_sql);
mysqli_stmt_bind_param($check_saved_stmt, "ii", $user_id, $job_id);
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
$insert_sql = "INSERT INTO saved_jobs (userid, jobid, saved_at) VALUES (?, ?, NOW())";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($insert_stmt, "ii", $user_id, $job_id);

if (mysqli_stmt_execute($insert_stmt)) {
    $saved_job_id = mysqli_insert_id($conn);

    // Fetch saved details
    $get_saved_sql = "SELECT sj.id, sj.userid, sj.jobid, sj.saved_at,
                             j.title, j.location, j.job_type, j.salary_min, j.salary_max
                      FROM saved_jobs sj
                      JOIN jobs j ON sj.jobid = j.id
                      WHERE sj.id = ?";
    $get_saved_stmt = mysqli_prepare($conn, $get_saved_sql);
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
    echo json_encode(["message" => "Failed to save job: " . mysqli_stmt_error($insert_stmt), "status" => false]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>
