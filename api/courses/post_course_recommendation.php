<?php
// post_course_recommendation.php - Insert course recommendation
require_once '../cors.php';

// ✅ Authenticate and allow only "admin" or "institute" roles
$decoded = authenticateJWT(['admin', 'institute','recruiter']); 

// ✅ Get role from JWT
$role = strtolower($decoded['role'] ?? '');

// ✅ Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$student_id = $input['student_id'] ?? null;
$course_id = $input['course_id'] ?? null;
$recommended_by = $input['recommended_by'] ?? $role; // 'admin', 'recruiter' or 'institute'
$recommended_at = $input['recommended_at'] ?? date('Y-m-d H:i:s');
$reason = $input['reason'] ?? null;
$admin_action = $input['admin_action'] ?? 'approved'; // 'pending', 'approved', 'rejected'

// ✅ Validation
if (!$student_id || !$course_id) {
    echo json_encode([
        "message" => "student_id and course_id are required",
        "status" => false
    ]);
    exit;
}

// ✅ Validate student exists
$check_student_sql = "SELECT id FROM student_profiles WHERE id = ?";
$check_student_stmt = mysqli_prepare($conn, $check_student_sql);
mysqli_stmt_bind_param($check_student_stmt, "i", $student_id);
mysqli_stmt_execute($check_student_stmt);
$student_result = mysqli_stmt_get_result($check_student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    echo json_encode([
        "message" => "Invalid student_id: Student not found",
        "status" => false
    ]);
    mysqli_stmt_close($check_student_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_student_stmt);

// ✅ Validate course exists
$check_course_sql = "SELECT id FROM courses WHERE id = ?";
$check_course_stmt = mysqli_prepare($conn, $check_course_sql);
mysqli_stmt_bind_param($check_course_stmt, "i", $course_id);
mysqli_stmt_execute($check_course_stmt);
$course_result = mysqli_stmt_get_result($check_course_stmt);

if (mysqli_num_rows($course_result) === 0) {
    echo json_encode([
        "message" => "Invalid course_id: Course not found",
        "status" => false
    ]);
    mysqli_stmt_close($check_course_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_course_stmt);

// ✅ Check if recommendation already exists (prevent duplicates)
$check_duplicate_sql = "SELECT id FROM recommendations WHERE student_id = ? AND course_id = ?";
$check_duplicate_stmt = mysqli_prepare($conn, $check_duplicate_sql);
mysqli_stmt_bind_param($check_duplicate_stmt, "ii", $student_id, $course_id);
mysqli_stmt_execute($check_duplicate_stmt);
$duplicate_result = mysqli_stmt_get_result($check_duplicate_stmt);

if (mysqli_num_rows($duplicate_result) > 0) {
    echo json_encode([
        "message" => "Recommendation already exists for this student and course",
        "status" => false
    ]);
    mysqli_stmt_close($check_duplicate_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_duplicate_stmt);

// ✅ INSERT recommendation
$insert_sql = "INSERT INTO recommendations (student_id, course_id, recommended_by, recommended_at, reason, admin_action) 
               VALUES (?, ?, ?, ?, ?, ?)";

$insert_stmt = mysqli_prepare($conn, $insert_sql);

if (!$insert_stmt) {
    echo json_encode([
        "message" => "Database prepare error: " . mysqli_error($conn),
        "status" => false
    ]);
    mysqli_close($conn);
    exit;
}

mysqli_stmt_bind_param($insert_stmt, "iissss", $student_id, $course_id, $recommended_by, $recommended_at, $reason, $admin_action);

if (mysqli_stmt_execute($insert_stmt)) {
    $recommendation_id = mysqli_insert_id($conn);
    
    echo json_encode([
        "message" => "Course recommendation created successfully",
        "status" => true,
        "recommendation_id" => $recommendation_id,
        "data" => [
            "student_id" => $student_id,
            "course_id" => $course_id,
            "recommended_by" => $recommended_by,
            "recommended_at" => $recommended_at,
            "reason" => $reason,
            "admin_action" => $admin_action
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        "message" => "Failed to create recommendation: " . mysqli_stmt_error($insert_stmt),
        "status" => false
    ]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>