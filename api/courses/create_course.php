<?php
// create_course.php - Create new course (Admin or Institute access)
require_once '../cors.php';

// Authenticate JWT
$decoded = authenticateJWT(['admin', 'institute']);
$user_role = $decoded['role'] ?? '';
$user_id   = $decoded['user_id'] ?? 0;

// ---------- POST: Create Course ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!in_array($user_role, ['admin', 'institute'])) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: Only admin or institute can create courses"
        ]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // ---------- Collect input data ----------
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
    $status         = 'Active';
    $admin_action   = 'pending';

    // institute_id from token
    $institute_id = ($user_role === 'institute') ? $user_id : ($data['institute_id'] ?? 0);

    // ---------- Validation ----------
    if (empty($title) || empty($description) || empty($duration) || $fee <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Required fields missing or invalid"
        ]);
        exit();
    }

    try {
        // ---------- SQL INSERT ----------
        $stmt = $conn->prepare("
            INSERT INTO courses (
                institute_id, course_code, title, description, course_type, prerequisites, level, credits,
                duration, fee, target_skills, teacher_name, min_students, max_students, start_date, end_date,
                registration_start_date, registration_end_date, grading_criteria, office_hours, office_location,
                exam_details, button_allowing_level, faqs, subject_title, module_description, media_path,
                is_certification_based, status, admin_action, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");

        // ✅ 31 placeholders → 31 bind params
        $stmt->bind_param(
            "isssssssidssiiissssssssssssiss",
            $institute_id,             // i
            $course_code,              // s
            $title,                    // s
            $description,              // s
            $course_type,              // s
            $prerequisites,            // s
            $level,                    // s
            $credits,                  // i
            $duration,                 // s
            $fee,                      // d
            $target_skills,            // s
            $teacher_name,             // s
            $min_students,             // i
            $max_students,             // i
            $start_date,               // s
            $end_date,                 // s
            $registration_start_date,  // s
            $registration_end_date,    // s
            $grading_criteria,         // s
            $office_hours,             // s
            $office_location,          // s
            $exam_details,             // s
            $button_allowing_level,    // s
            $faqs,                     // s
            $subject_title,            // s
            $module_description,       // s
            $media_path,               // s
            $is_certification_based,   // i
            $status,                   // s
            $admin_action              // s
        );

        // ---------- Execute ----------
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Course created successfully",
                "course_id" => $stmt->insert_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to create course",
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
?>
