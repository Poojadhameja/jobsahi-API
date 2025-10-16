<?php
// get_course_by_id.php – Fetch a single course by ID (Admin / Student visibility)
require_once '../cors.php';

try {
    // 🔐 Authenticate user and determine role
    $user = authenticateJWT(['admin', 'student', 'institute']);
    $user_role = $user['role'] ?? 'student';
    $user_id   = $user['user_id'] ?? ($user['id'] ?? null);

    // ---------- Validate Request ----------
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            "status" => false,
            "message" => "Method not allowed. Use GET request."
        ]);
        exit;
    }

    if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Valid Course ID is required."
        ]);
        exit;
    }

    $course_id = intval($_GET['id']);

    // ---------- Role-Based SQL ----------
    if ($user_role === 'admin') {
        // Admin can view all (pending + approved + rejected)
        $sql = "
            SELECT 
                id, institute_id, course_code, title, description, course_type, prerequisites,
                level, credits, duration, fee, target_skills, teacher_name, min_students,
                max_students, start_date, end_date, registration_start_date, registration_end_date,
                grading_criteria, office_hours, office_location, exam_details, button_allowing_level,
                faqs, subject_title, module_description, media_path, is_certification_based,
                status, admin_action, created_at, updated_at
            FROM courses
            WHERE id = ?
        ";
    } elseif ($user_role === 'institute') {
        // Institute can only see its own courses (any admin_action)
        $sql = "
            SELECT 
                id, institute_id, course_code, title, description, course_type, prerequisites,
                level, credits, duration, fee, target_skills, teacher_name, min_students,
                max_students, start_date, end_date, registration_start_date, registration_end_date,
                grading_criteria, office_hours, office_location, exam_details, button_allowing_level,
                faqs, subject_title, module_description, media_path, is_certification_based,
                status, admin_action, created_at, updated_at
            FROM courses
            WHERE id = ? AND institute_id = ?
        ";
    } else {
        // Students or public users: can see only approved courses
        $sql = "
            SELECT 
                id, institute_id, course_code, title, description, course_type, prerequisites,
                level, credits, duration, fee, target_skills, teacher_name, min_students,
                max_students, start_date, end_date, registration_start_date, registration_end_date,
                grading_criteria, office_hours, office_location, exam_details, button_allowing_level,
                faqs, subject_title, module_description, media_path, is_certification_based,
                status, created_at, updated_at
            FROM courses
            WHERE id = ? AND admin_action = 'approved' AND status = 'Active'
        ";
    }

    // ---------- Prepare Statement ----------
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if ($user_role === 'institute') {
        $stmt->bind_param("ii", $course_id, $user_id);
    } else {
        $stmt->bind_param("i", $course_id);
    }

    // ---------- Execute and Fetch ----------
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Course not found or not accessible to your role."
        ]);
        exit;
    }

    $course = $result->fetch_assoc();

    // ---------- Output ----------
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Course retrieved successfully",
        "course" => $course,
        "user_role" => $user_role
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching course: " . $e->getMessage()
    ]);

    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
