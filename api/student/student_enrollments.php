<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php'; // JWT helper
require_once '../auth/auth_middleware.php'; // middleware

// Authenticate user for all roles
$decoded = authenticateJWT(['admin', 'student']); 

// Get user role from decoded token
$user_role = isset($decoded['role']) ? $decoded['role'] : '';

// Validate student_id from GET
if (!isset($_GET['student_id']) || intval($_GET['student_id']) <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Invalid or missing student_id"
    ]);
    exit;
}

$student_id = intval($_GET['student_id']);

try {
    // Base SQL: join enrollments with courses
    $sql = "SELECT 
                e.id AS enrollment_id,
                c.id AS course_id,
                c.title,
                c.description,
                c.duration,
                c.fee,
                c.institute_id,
                c.admin_action
            FROM student_course_enrollments e
            INNER JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ?";

    // Append admin_action filter for non-admins
    if ($user_role !== 'admin') {
        $sql .= " AND c.admin_action = 'approval'";
    }

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

?>
