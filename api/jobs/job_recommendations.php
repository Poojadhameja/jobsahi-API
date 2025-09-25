<?php
// get_recommended_jobs.php - Fetch recommended jobs for a student (JWT - Role-based visibility)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate and allow "student", "admin"
$decoded = authenticateJWT(['admin', 'student']); 

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

// ✅ Get role & student ID from JWT payload
$role = strtolower($decoded['role'] ?? '');
$student_id = $decoded['id'] ?? $decoded['user_id'] ?? $decoded['student_id'] ?? null;

// Validate student_id for students
if ($role === 'student') {
    if (!$student_id || $student_id <= 0) {
        echo json_encode([
            "message" => "Invalid token: student ID missing or invalid", 
            "status" => false
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
}

// ✅ Build SQL query with role-based visibility
if ($role === 'admin') {
    // Admin can see both pending + approved
    $get_recommend_sql = "
        SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, jr.score, jr.created_at,
               j.title, j.location, j.job_type, j.salary_min, j.salary_max, j.status, j.admin_action
        FROM job_recommendations jr
        JOIN jobs j ON jr.job_id = j.id
        " . ($role === 'student' ? "WHERE jr.student_id = ?" : "") . "
        ORDER BY jr.score DESC, jr.created_at DESC
    ";
} else {
    // Students, Recruiters, Institutes → Only approved jobs
    $get_recommend_sql = "
        SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, jr.score, jr.created_at,
               j.title, j.location, j.job_type, j.salary_min, j.salary_max, j.status, j.admin_action
        FROM job_recommendations jr
        JOIN jobs j ON jr.job_id = j.id
        WHERE j.admin_action = 'approved'
        " . ($role === 'student' ? "AND jr.student_id = ?" : "") . "
        ORDER BY jr.score DESC, jr.created_at DESC
    ";
}

// ✅ Prepare query
$get_recommend_stmt = mysqli_prepare($conn, $get_recommend_sql);
if (!$get_recommend_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// ✅ Bind params only if student role
if ($role === 'student') {
    mysqli_stmt_bind_param($get_recommend_stmt, "i", $student_id);
}

mysqli_stmt_execute($get_recommend_stmt);
$recommend_result = mysqli_stmt_get_result($get_recommend_stmt);

$recommended_jobs = [];
while ($row = mysqli_fetch_assoc($recommend_result)) {
    $recommended_jobs[] = $row;
}

mysqli_stmt_close($get_recommend_stmt);
mysqli_close($conn);

// ✅ Response
if (empty($recommended_jobs)) {
    echo json_encode([
        "message" => "No recommended jobs found",
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
