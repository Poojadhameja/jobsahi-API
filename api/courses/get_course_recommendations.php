<?php
// get_course_recommendations.php - Fetch course recommendations (JWT - Role-based visibility)
require_once '../cors.php';

// ✅ Authenticate and allow "admin", "institute", "recruiter"
$decoded = authenticateJWT(['admin', 'institute', 'recruiter']); 

// ✅ Get role from JWT payload
$role = strtolower($decoded['role'] ?? '');

// ✅ Build SQL query with role-based visibility
if ($role === 'admin') {
    // Admin sees ALL recommendations regardless of admin_action
    $get_recommend_sql = "
        SELECT id AS recommendation_id, student_id, course_id, recommended_by, 
               recommended_at, reason, admin_action
        FROM recommendations
        ORDER BY recommended_at DESC
    ";
} elseif ($role === 'institute' || $role === 'recruiter') {
    // Institute and Recruiter see only approved recommendations
    $get_recommend_sql = "
        SELECT id AS recommendation_id, student_id, course_id, recommended_by, 
               recommended_at, reason, admin_action
        FROM recommendations
        WHERE admin_action = 'approved'
        ORDER BY recommended_at DESC
    ";
} else {
    echo json_encode([
        "message" => "Unauthorized role: $role",
        "status" => false
    ]);
    exit;
}

// ✅ Prepare query
$get_recommend_stmt = mysqli_prepare($conn, $get_recommend_sql);
if (!$get_recommend_stmt) {
    echo json_encode([
        "message" => "Database prepare error: " . mysqli_error($conn), 
        "status" => false
    ]);
    exit;
}

mysqli_stmt_execute($get_recommend_stmt);
$recommend_result = mysqli_stmt_get_result($get_recommend_stmt);

$recommended_courses = [];
while ($row = mysqli_fetch_assoc($recommend_result)) {
    $recommended_courses[] = $row;
}

mysqli_stmt_close($get_recommend_stmt);
mysqli_close($conn);

// ✅ Response
if (empty($recommended_courses)) {
    echo json_encode([
        "message" => "No course recommendations found",
        "status" => true,
        "data" => [],
        "role" => $role,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "message" => "Course recommendations fetched successfully",
        "status" => true,
        "count" => count($recommended_courses),
        "role" => $role,
        "data" => $recommended_courses,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
}
?>