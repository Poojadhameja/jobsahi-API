<?php
<<<<<<< HEAD
// get_recommended_jobs.php - Fetch recommended jobs for a student (JWT - Student Only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate and allow only "student" role
$decoded = authenticateJWT('student');  // decoded JWT payload
=======
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

<<<<<<< HEAD
// ✅ Student ID from JWT payload (instead of query param)
$student_id = $decoded['id'] ?? $decoded['user_id'] ?? $decoded['student_id'] ?? null;
=======
// Get student_id from query params
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

// Validate student_id
if (!$student_id || $student_id <= 0) {
    echo json_encode([
<<<<<<< HEAD
        "message" => "Invalid token: student ID missing or invalid", 
        "status" => false
=======
        "message" => "Student ID is required and must be a positive integer", 
        "status" => false,
        "received_student_id" => $student_id
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
    ]);
    exit;
}

// Check if student exists
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

// Fetch recommended jobs with extra columns (source, score, created_at)
$get_recommend_sql = "
    SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, jr.score, jr.created_at,
           j.title, j.location, j.job_type, j.salary_min, j.salary_max, j.status
    FROM job_recommendations jr
    JOIN jobs j ON jr.job_id = j.id
    WHERE jr.student_id = ?
    ORDER BY jr.score DESC, jr.created_at DESC
";

<<<<<<< HEAD
=======

>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
$get_recommend_stmt = mysqli_prepare($conn, $get_recommend_sql);
if (!$get_recommend_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($get_recommend_stmt, "i", $student_id);
mysqli_stmt_execute($get_recommend_stmt);
$recommend_result = mysqli_stmt_get_result($get_recommend_stmt);

$recommended_jobs = [];
while ($row = mysqli_fetch_assoc($recommend_result)) {
    $recommended_jobs[] = $row;
}
mysqli_stmt_close($get_recommend_stmt);
mysqli_close($conn);

// Response
if (empty($recommended_jobs)) {
    echo json_encode([
        "message" => "No recommended jobs found for this student",
        "status" => true,
        "data" => [],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "message" => "Recommended jobs fetched successfully",
        "status" => true,
        "count" => count($recommended_jobs),
        "data" => $recommended_jobs,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}
?>
