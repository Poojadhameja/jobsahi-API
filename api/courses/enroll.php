<?php
// enroll.php - Enroll a student into a course
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

<<<<<<< HEAD
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
require_once '../db.php';

// Authenticate and check for student role
authenticateJWT('student');

=======
require_once '../db.php';

>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed", "status" => false]);
    exit();
}

// Get course_id from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["message" => "Course ID is required", "status" => false]);
    exit();
}
$course_id = intval($_GET['id']);

// Get student_id from request body (JSON)
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['student_id']) || empty($data['student_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "Student ID is required", "status" => false]);
    exit();
}
$student_id = intval($data['student_id']);

// Check if course exists
$course_check = "SELECT id FROM courses WHERE id = ?";
if ($stmt = mysqli_prepare($conn, $course_check)) {
    mysqli_stmt_bind_param($stmt, "i", $course_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) === 0) {
        http_response_code(404);
        echo json_encode(["message" => "Course not found", "status" => false]);
        exit();
    }
    mysqli_stmt_close($stmt);
}

// Check if already enrolled
$check_enroll = "SELECT id FROM student_course_enrollments WHERE student_id = ? AND course_id = ?";
if ($stmt = mysqli_prepare($conn, $check_enroll)) {
    mysqli_stmt_bind_param($stmt, "ii", $student_id, $course_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        http_response_code(response_code: 409);
        echo json_encode(["message" => "Already enrolled in this course", "status" => false]);
        exit();
    }
    mysqli_stmt_close($stmt);
}

// Insert enrollment (match table columns)
$sql = "INSERT INTO student_course_enrollments 
        (student_id, course_id, enrollment_date, status, created_at, modified_at) 
        VALUES (?, ?, NOW(), 'enrolled', NOW(), NOW())";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $student_id, $course_id);

    if (mysqli_stmt_execute($stmt)) {
        http_response_code(201);
        echo json_encode(["message" => "Enrolled successfully", "status" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Failed to enroll", "status" => false]);
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Database error", "status" => false]);
}

mysqli_close($conn);
<<<<<<< HEAD
?>
=======
?>
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
