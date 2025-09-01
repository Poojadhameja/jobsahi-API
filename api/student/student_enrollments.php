<?php
// student_enrollments.php - List student enrolled courses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../db.php';
<<<<<<< HEAD
require_once '../jwt_token/jwt_helper.php'; // include your JWT helper
require_once '../auth/auth_middleware.php'; // include middleware

// Authenticate JWT for student role
authenticateJWT('student'); // <-- this will check JWT and role
=======
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

// Get student_id from query params
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Invalid or missing student_id"
    ]);
    exit;
}

try {
    // SQL: join student_course_enrollments with courses
    $sql = "SELECT 
                e.id as enrollment_id,
                c.id as course_id,
                c.title,
                c.description,
                c.duration,
                c.fee,
                c.institute_id
            FROM student_course_enrollments e
            INNER JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $enrollments = [];
        while ($row = $result->fetch_assoc()) {
            $enrollments[] = $row;
        }

        http_response_code(200);
        echo json_encode([
            "status" => true,
            "data" => $enrollments
        ]);
    } else {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
}
<<<<<<< HEAD

?>
=======
?>
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
