<?php
// get_recommendation_by_id.php - Fetch a single job recommendation by ID
require_once '../cors.php';

// ✅ Authenticate and allow "admin", "institute", "student"
$decoded = authenticateJWT(['admin', 'institute', 'student']); 

// ✅ Get role & IDs from JWT payload
$role = strtolower($decoded['role'] ?? '');
$user_id = $decoded['id'] ?? $decoded['user_id'] ?? null;
$student_id = $decoded['student_id'] ?? null;

// If role = student, resolve student_id from student_profiles
if ($role === 'student') {
    if (!$user_id || $user_id <= 0) {
        echo json_encode([
            "message" => "Invalid token: user ID missing or invalid", 
            "status" => false
        ]);
        exit;
    }

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

    $student_id = $map_row['id'];
    mysqli_stmt_close($map_stmt);
}

// ✅ Get recommendation ID from URL parameter
$recommendation_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$recommendation_id || $recommendation_id <= 0) {
    echo json_encode([
        "message" => "Valid recommendation ID is required", 
        "status" => false
    ]);
    exit;
}

// ✅ Build SQL query with role-based visibility
if ($role === 'admin') {
    // Admin can see all recommendations
    $get_sql = "
        SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, 
               jr.score, jr.admin_action, jr.created_at,
               j.title, j.location, j.job_type, j.salary_min, 
               j.salary_max, j.status, j.admin_action AS job_admin_action,
               j.created_at AS job_created_at
        FROM job_recommendations jr
        JOIN jobs j ON jr.job_id = j.id
        WHERE jr.id = ?
    ";
    $get_stmt = mysqli_prepare($conn, $get_sql);
    mysqli_stmt_bind_param($get_stmt, "i", $recommendation_id);
} elseif ($role === 'institute') {
    // Institute can see approved recommendations for their students
    $get_sql = "
        SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, 
               jr.score, jr.admin_action, jr.created_at,
               j.title, j.location, j.job_type, j.salary_min, 
               j.salary_max, j.status, j.admin_action AS job_admin_action,
               j.created_at AS job_created_at
        FROM job_recommendations jr
        JOIN jobs j ON jr.job_id = j.id
        WHERE jr.id = ? AND j.admin_action = 'approved'
    ";
    $get_stmt = mysqli_prepare($conn, $get_sql);
    mysqli_stmt_bind_param($get_stmt, "i", $recommendation_id);
} else {
    // Student can only see their own approved recommendations
    $get_sql = "
        SELECT jr.id AS recommendation_id, jr.student_id, jr.job_id, jr.source, 
               jr.score, jr.admin_action, jr.created_at,
               j.title, j.location, j.job_type, j.salary_min, 
               j.salary_max, j.status, j.admin_action AS job_admin_action,
               j.created_at AS job_created_at
        FROM job_recommendations jr
        JOIN jobs j ON jr.job_id = j.id
        WHERE jr.id = ? AND jr.student_id = ? AND j.admin_action = 'approved'
    ";
    $get_stmt = mysqli_prepare($conn, $get_sql);
    mysqli_stmt_bind_param($get_stmt, "ii", $recommendation_id, $student_id);
}

// ✅ Execute query
if (!$get_stmt) {
    echo json_encode([
        "message" => "Database prepare error: " . mysqli_error($conn), 
        "status" => false
    ]);
    exit;
}

mysqli_stmt_execute($get_stmt);
$result = mysqli_stmt_get_result($get_stmt);
$recommendation = mysqli_fetch_assoc($result);

mysqli_stmt_close($get_stmt);
mysqli_close($conn);

// ✅ Response
if (!$recommendation) {
    echo json_encode([
        "message" => "Recommendation not found or access denied",
        "status" => false,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "message" => "Recommendation fetched successfully",
        "status" => true,
        "data" => $recommendation,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}
?>