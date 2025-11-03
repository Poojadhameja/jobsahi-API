<?php
// unsave_course.php - Remove saved course for student (JWT required)
require_once '../cors.php';

// ✅ Authenticate JWT (only student can access)
$decoded = authenticateJWT('student'); 

// Handle different key names from JWT payload safely
$student_id = null;
if (isset($decoded['id'])) {
    $student_id = $decoded['id'];
} elseif (isset($decoded['user_id'])) {
    $student_id = $decoded['user_id'];
} elseif (isset($decoded['student_id'])) {
    $student_id = $decoded['student_id'];
}

if (!$student_id) {
    echo json_encode([
        "message" => "Invalid token payload: student id missing",
        "status"  => false
    ]);
    exit;
}

// Get input data - Handle both JSON and form data
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
$course_id = null;

if (strpos($content_type, "application/json") !== false) {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["message" => "Invalid JSON format: " . json_last_error_msg(), "status" => false]);
        exit;
    }
    
    $course_id = isset($input['course_id']) ? intval($input['course_id']) : null;
} else {
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
}

// Validation
if (!$course_id || $course_id <= 0) {
    echo json_encode([
        "message" => "Course ID is required and must be a positive integer",
        "status" => false,
        "received_course_id" => $course_id
    ]);
    exit;
}

// ✅ Check if student exists in student_profiles table
$check_student_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
$check_student_stmt = mysqli_prepare($conn, $check_student_sql);
mysqli_stmt_bind_param($check_student_stmt, "i", $student_id);
mysqli_stmt_execute($check_student_stmt);
$student_result = mysqli_stmt_get_result($check_student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    echo json_encode([
        "message" => "Student profile not found. Please complete your profile.", 
        "status" => false,
        "student_id" => $student_id
    ]);
    mysqli_stmt_close($check_student_stmt);
    mysqli_close($conn);
    exit;
}

// Get the actual student profile ID
$student_profile = mysqli_fetch_assoc($student_result);
$student_profile_id = $student_profile['id'];
mysqli_stmt_close($check_student_stmt);

// ✅ Check if course is saved by this student
$check_sql = "SELECT sc.id, c.title 
              FROM saved_courses sc
              JOIN courses c ON sc.course_id = c.id
              WHERE sc.student_id = ? AND sc.course_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $student_profile_id, $course_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode([
        "message" => "Course is not saved by you or doesn't exist",
        "status" => false
    ]);
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    exit;
}

$saved_course_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($check_stmt);

// ✅ Hard delete from saved_courses table
$delete_sql = "DELETE FROM saved_courses WHERE student_id = ? AND course_id = ?";
$delete_stmt = mysqli_prepare($conn, $delete_sql);
mysqli_stmt_bind_param($delete_stmt, "ii", $student_profile_id, $course_id);

if (mysqli_stmt_execute($delete_stmt)) {
    echo json_encode([
        "message" => "Course removed from saved list successfully",
        "status" => true,
        "data" => [
            "course_id" => $course_id,
            "course_title" => $saved_course_data['title'],
            "student_id" => $student_profile_id
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(["message" => "Failed to remove course: " . mysqli_stmt_error($delete_stmt), "status" => false]);
}

mysqli_stmt_close($delete_stmt);
mysqli_close($conn);
?>


