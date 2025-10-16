<?php
// update_course.php – Update existing course (Admin / Institute access)
require_once '../cors.php';

try {
    // 🔐 Authenticate JWT
    $decoded = authenticateJWT(['admin', 'institute']);
    $user_role = $decoded['role'] ?? '';
    $user_id   = $decoded['user_id'] ?? 0;

    // ---------- Validate Course ID ----------
    $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($course_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid or missing course ID"
        ]);
        exit();
    }

    // ---------- Parse Input ----------
    $data = json_decode(file_get_contents("php://input"), true);

    // Collect updatable fields (based on your table structure)
    $course_code   = $data['course_code'] ?? '';
    $title         = $data['title'] ?? '';
    $description   = $data['description'] ?? '';
    $course_type   = $data['course_type'] ?? '';
    $prerequisites = $data['prerequisites'] ?? '';
    $level         = $data['level'] ?? '';
    $credits       = isset($data['credits']) ? intval($data['credits']) : 0;
    $duration      = $data['duration'] ?? '';
    $fee           = isset($data['fee']) ? floatval($data['fee']) : 0;
    $target_skills = $data['target_skills'] ?? '';
    $teacher_name  = $data['teacher_name'] ?? '';
    $min_students  = isset($data['min_students']) ? intval($data['min_students']) : 0;
    $max_students  = isset($data['max_students']) ? intval($data['max_students']) : 0;
    $start_date    = $data['start_date'] ?? null;
    $end_date      = $data['end_date'] ?? null;
    $registration_start_date = $data['registration_start_date'] ?? null;
    $registration_end_date   = $data['registration_end_date'] ?? null;
    $grading_criteria = $data['grading_criteria'] ?? '';
    $office_hours   = $data['office_hours'] ?? '';
    $office_location = $data['office_location'] ?? '';
    $exam_details   = $data['exam_details'] ?? '';
    $button_allowing_level = $data['button_allowing_level'] ?? '';
    $faqs           = $data['faqs'] ?? '';
    $subject_title  = $data['subject_title'] ?? '';
    $module_description = $data['module_description'] ?? '';
    $media_path     = $data['media_path'] ?? '';
    $is_certification_based = isset($data['is_certification_based']) ? intval($data['is_certification_based']) : 0;

    // Validation
    if (empty($title) || empty($description) || empty($duration) || $fee <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Title, Description, Duration, and Fee are required"
        ]);
        exit();
    }

    // ---------- Check if course exists ----------
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
    $current_admin_action = $course['admin_action'];
    $course_institute_id  = $course['institute_id'];

    // ---------- Role-based permissions ----------
    if ($user_role === 'institute' && $course_institute_id != $user_id) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: You can only update your own courses"
        ]);
        exit();
    }

    // Admin can override admin_action, Institute cannot
    if ($user_role === 'admin') {
        $admin_action = $data['admin_action'] ?? $current_admin_action;
    } else {
        $admin_action = $current_admin_action;
    }

    // ---------- Build update query ----------
    $stmt = $conn->prepare("
        UPDATE courses SET
            course_code = ?, title = ?, description = ?, course_type = ?, prerequisites = ?,
            level = ?, credits = ?, duration = ?, fee = ?, target_skills = ?, teacher_name = ?,
            min_students = ?, max_students = ?, start_date = ?, end_date = ?,
            registration_start_date = ?, registration_end_date = ?, grading_criteria = ?,
            office_hours = ?, office_location = ?, exam_details = ?, button_allowing_level = ?,
            faqs = ?, subject_title = ?, module_description = ?, media_path = ?,
            is_certification_based = ?, admin_action = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssssisdssiiissssssssssssiss",
        $course_code,
        $title,
        $description,
        $course_type,
        $prerequisites,
        $level,
        $credits,
        $duration,
        $fee,
        $target_skills,
        $teacher_name,
        $min_students,
        $max_students,
        $start_date,
        $end_date,
        $registration_start_date,
        $registration_end_date,
        $grading_criteria,
        $office_hours,
        $office_location,
        $exam_details,
        $button_allowing_level,
        $faqs,
        $subject_title,
        $module_description,
        $media_path,
        $is_certification_based,
        $admin_action,
        $course_id
    );

    // ---------- Execute ----------
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Course updated successfully",
                "course_id" => $course_id,
                "updated_by" => $user_role
            ]);
        } else {
            echo json_encode([
                "status" => true,
                "message" => "No changes detected (same values as before)",
                "course_id" => $course_id,
                "updated_by" => $user_role
            ]);
        }
    } else {
        throw new Exception($stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error updating course: " . $e->getMessage()
    ]);

    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
