<?php
// update_course.php - Update existing course (Admin or Institute)
require_once '../cors.php';

try {
    // ✅ Authenticate JWT and allow Admin + Institute
    $decoded = authenticateJWT(['admin', 'institute']); 
    $role = $decoded['role'] ?? '';
    $user_id = $decoded['user_id'] ?? ($decoded['id'] ?? null);
    $institute_id = $decoded['institute_id'] ?? $user_id;

    // ✅ Validate course ID
    $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($course_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid or missing course ID"
        ]);
        exit();
    }

    // ✅ Read and parse JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    $title        = trim($data['title'] ?? '');
    $description  = trim($data['description'] ?? '');
    $duration     = trim($data['duration'] ?? '');
    $fee          = floatval($data['fee'] ?? 0);
    $category     = trim($data['category'] ?? '');
    $tagged_skills = trim($data['tagged_skills'] ?? '');
    $batch_limits = intval($data['batch_limits'] ?? 0);
    $course_status = trim($data['course_status'] ?? '');
    $instructor_name = trim($data['instructor_name'] ?? '');
    $mode          = trim($data['mode'] ?? '');
    $certification_allowed = isset($data['certification_allowed']) ? (int)$data['certification_allowed'] : 0;

    // ✅ Validation
    if (
        empty($title) || empty($description) || empty($duration) || $fee <= 0 ||
        empty($category) || empty($instructor_name) || empty($mode) || $batch_limits <= 0
    ) {
        echo json_encode([
            "status" => false,
            "message" => "All required fields must be filled"
        ]);
        exit();
    }

    // ✅ Check if the course exists and role is allowed
    $check = $conn->prepare("SELECT institute_id, admin_action FROM courses WHERE id = ?");
    $check->bind_param("i", $course_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Course not found"
        ]);
        exit();
    }

    $course = $result->fetch_assoc();
    $course_institute_id = $course['institute_id'];
    $current_admin_action = $course['admin_action'];

    // ✅ Permission check
    if ($role === 'institute' && intval($course_institute_id) !== intval($institute_id)) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: You can only update your own courses"
        ]);
        exit();
    }

    // ✅ Admin can change status, institute cannot
    if ($role === 'admin') {
        $admin_action = trim($data['admin_action'] ?? $current_admin_action);
    } else {
        $admin_action = $current_admin_action;
    }

    // ✅ Update query
    $stmt = $conn->prepare("
        UPDATE courses 
        SET 
            title = ?, 
            description = ?, 
            duration = ?, 
            fee = ?, 
            category = ?, 
            tagged_skills = ?, 
            batch_limits = ?, 
            course_status = ?, 
            instructor_name = ?, 
            mode = ?, 
            certification_allowed = ?, 
            admin_action = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssdsissssisi",
        $title,
        $description,
        $duration,
        $fee,
        $category,
        $tagged_skills,
        $batch_limits,
        $course_status,
        $instructor_name,
        $mode,
        $certification_allowed,
        $admin_action,
        $course_id
    );

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Course updated successfully",
            "course_id" => $course_id,
            "updated_by" => $role
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update course: " . $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
