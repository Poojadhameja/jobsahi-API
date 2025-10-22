<?php
// update_course.php - Update existing course (Admin or Institute)
require_once '../cors.php';

try {
    // ✅ Authenticate JWT (Admin + Institute)
    $decoded = authenticateJWT(['admin', 'institute']); 
    $role = $decoded['role'] ?? '';
    $user_id = $decoded['user_id'] ?? ($decoded['id'] ?? null);
    $institute_id = $decoded['institute_id'] ?? $user_id;

    // ✅ Validate Course ID
    $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($course_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid or missing course ID"
        ]);
        exit();
    }

    // ✅ Parse JSON body
    $data = json_decode(file_get_contents("php://input"), true);

    // ✅ Extract all fields according to current table
    $title        = trim($data['title'] ?? '');
    $description  = trim($data['description'] ?? '');
    $duration     = trim($data['duration'] ?? '');
    $fee          = floatval($data['fee'] ?? 0);
    $category_id  = intval($data['category_id'] ?? 0);
    $tagged_skills = trim($data['tagged_skills'] ?? '');
    $batch_limit  = intval($data['batch_limit'] ?? 0);
    $status       = trim($data['status'] ?? '');
    $instructor_name = trim($data['instructor_name'] ?? '');
    $mode         = trim($data['mode'] ?? '');
    $certification_allowed = isset($data['certification_allowed']) ? (int)$data['certification_allowed'] : 0;
    $module_title = trim($data['module_title'] ?? '');
    $module_description = trim($data['module_description'] ?? '');
    $media        = trim($data['media'] ?? '');

    // ✅ Validate Required Fields
    if (
        empty($title) || empty($description) || empty($duration) ||
        $fee <= 0 || empty($instructor_name) || empty($mode)
    ) {
        echo json_encode([
            "status" => false,
            "message" => "All required fields must be filled properly."
        ]);
        exit();
    }

    // ✅ Check if course exists
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

    // ✅ Permission: Institute can only edit its own courses
    if ($role === 'institute' && intval($course_institute_id) !== intval($institute_id)) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: You can only update your own courses"
        ]);
        exit();
    }

    // ✅ Admin can change admin_action; institute cannot
    if ($role === 'admin') {
        $admin_action = trim($data['admin_action'] ?? $current_admin_action);
    } else {
        $admin_action = $current_admin_action;
    }

    // ✅ Update query according to your DB columns
    $stmt = $conn->prepare("
        UPDATE courses 
        SET 
            title = ?, 
            description = ?, 
            duration = ?, 
            fee = ?, 
            category_id = ?, 
            tagged_skills = ?, 
            batch_limit = ?, 
            status = ?, 
            instructor_name = ?, 
            mode = ?, 
            certification_allowed = ?, 
            module_title = ?, 
            module_description = ?, 
            media = ?, 
            admin_action = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssdi ss sss sssssi",
        $title,
        $description,
        $duration,
        $fee,
        $category_id,
        $tagged_skills,
        $batch_limit,
        $status,
        $instructor_name,
        $mode,
        $certification_allowed,
        $module_title,
        $module_description,
        $media,
        $admin_action,
        $course_id
    );

    // ✅ Execute Update
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
