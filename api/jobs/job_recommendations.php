<?php
// get_recommended_jobs.php - Fetch recommended jobs for a student (JWT - Role-based visibility)
require_once '../cors.php';

// ✅ Authenticate and allow "institute", "admin"
$decoded = authenticateJWT(['admin', 'institute']); 

// ✅ Get role & IDs from JWT payload
$role = strtolower($decoded['role'] ?? '');
$user_id = $decoded['id'] ?? $decoded['user_id'] ?? null;
$student_id = $decoded['student_id'] ?? null;

// If role = student, always resolve student_id from student_profiles
if ($role === 'student') {
    if (!$user_id || $user_id <= 0) {
        echo json_encode([
            "message" => "Invalid token: user ID missing or invalid", 
            "status" => false
        ]);
        exit;
    }

    // ✅ Map user → student profile
    $map_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
    $map_stmt = mysqli_prepare($conn, $map_sql);
    mysqli_stmt_bind_param($map_stmt, "i", $user_id);
    mysqli_stmt_execute($map_stmt);
    $map_result = mysqli_stmt_get_result($map_stmt);
    $map_row = mysqli_fetch_assoc($map_result);

    if (!$map_row) {
        echo json_encode([
            "message" => "Student profile not found for user_id: $user_id", 
            "status" => false
        ]);
        exit;
    }

    $student_id = $map_row['id']; // ✅ Use student_profiles.id as student_id
    mysqli_stmt_close($map_stmt);
}

// ✅ Build SQL query with role-based visibility
if ($role === 'admin') {
    $get_recommend_sql = "
        SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, jr.score, jr.created_at,
               j.title, j.location, j.job_type, j.salary_min, j.salary_max, j.status, j.admin_action
        FROM job_recommendations jr
        JOIN jobs j ON jr.job_id = j.id
        ORDER BY jr.score DESC, jr.created_at DESC
    ";
} else {
    $get_recommend_sql = "
        SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, jr.score, jr.created_at,
               j.title, j.location, j.job_type, j.salary_min, j.salary_max, j.status, j.admin_action
        FROM job_recommendations jr
        JOIN jobs j ON jr.job_id = j.id
        WHERE j.admin_action = 'approved'
          AND jr.student_id = ?
        ORDER BY jr.score DESC, jr.created_at DESC
    ";
}

// ✅ Prepare query
$get_recommend_stmt = mysqli_prepare($conn, $get_recommend_sql);
if (!$get_recommend_stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// ✅ Bind only for students
if ($role === 'institute') {
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
        "student_id" => $student_id,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "message" => "Recommended jobs fetched successfully",
        "status" => true,
        "count" => count($recommended_jobs),
        "student_id" => $student_id,
        "data" => $recommended_jobs,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}
?>
