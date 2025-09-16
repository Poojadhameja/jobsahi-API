<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// POST request → Issue certificate
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only allow admin and institute to issue certificates
    $decoded = authenticateJWT(['admin','institute']); 
    $user_id = $decoded['user_id'];

    $data = json_decode(file_get_contents("php://input"), true);

    $student_id   = isset($data['student_id']) ? intval($data['student_id']) : 0;
    $course_id    = isset($data['course_id']) ? intval($data['course_id']) : 0;
    $file_url     = isset($data['file_url']) ? trim($data['file_url']) : '';
    $issue_date   = isset($data['issue_date']) ? $data['issue_date'] : date('Y-m-d');
    $admin_action = isset($data['admin_action']) ? $data['admin_action'] : 'pending';

    if ($student_id <= 0 || $course_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Valid Student ID and Course ID are required"
        ]);
        exit();
    }

    try {
        // Check if certificate already exists for this student-course combination
        $duplicate_check = $conn->prepare("SELECT id FROM certificates WHERE student_id = ? AND course_id = ?");
        $duplicate_check->bind_param("ii", $student_id, $course_id);
        $duplicate_check->execute();
        $duplicate_result = $duplicate_check->get_result();

        if ($duplicate_result->num_rows > 0) {
            echo json_encode([
                "status" => false,
                "message" => "Certificate already exists for this student and course combination"
            ]);
            exit();
        }

        // Check student exists
        $student_check = $conn->prepare("SELECT id FROM student_profiles WHERE id = ?");
        $student_check->bind_param("i", $student_id);
        $student_check->execute();
        $student_result = $student_check->get_result();

        if ($student_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Student with ID $student_id does not exist"
            ]);
            exit();
        }

        // Check course exists
        $course_check = $conn->prepare("SELECT id, title FROM courses WHERE id = ?");
        $course_check->bind_param("i", $course_id);
        $course_check->execute();
        $course_result = $course_check->get_result();

        if ($course_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Course with ID $course_id does not exist"
            ]);
            exit();
        }
        $course_data = $course_result->fetch_assoc();

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue_date)) {
            echo json_encode([
                "status" => false,
                "message" => "Invalid date format. Use YYYY-MM-DD"
            ]);
            exit();
        }

        // Insert certificate
        $stmt = $conn->prepare("INSERT INTO certificates (student_id, course_id, file_url, issue_date, admin_action) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $student_id, $course_id, $file_url, $issue_date, $admin_action);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Certificate issued successfully",
                "certificate_id" => $stmt->insert_id,
                "course_title" => $course_data['title']
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to issue certificate",
                "error" => $stmt->error
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            "status" => false,
            "message" => "Error: " . $e->getMessage()
        ]);
    }

    $conn->close();
    exit();
}

// If neither GET nor POST
echo json_encode([
    "status" => false,
    "message" => "Method not allowed"
]);
?>
