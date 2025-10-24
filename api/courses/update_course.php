<?php
// update_course.php - Update existing course (Admin or Institute)
require_once '../cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
    try {
        // ✅ Authenticate JWT (Admin + Institute)
        $decoded = authenticateJWT(['admin', 'institute']); 
        $role = strtolower($decoded['role'] ?? '');
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

        // ✅ Extract and sanitize all fields
        $title        = trim($data['title'] ?? '');
        $description  = trim(strip_tags($data['description'] ?? '')); // removes <p>, <br>, etc.
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
        $module_description = trim(strip_tags($data['module_description'] ?? ''));
        $media        = trim($data['media'] ?? '');

        // ✅ Validate Required Fields
        if (empty($title) || empty($description) || empty($duration) ||
            $fee <= 0 || empty($instructor_name) || empty($mode)) {
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
        $course_institute_id = intval($course['institute_id']);
        $current_admin_action = $course['admin_action'];
        $check->close();

        // ✅ Permission check
        if ($role === 'institute' && $course_institute_id !== intval($institute_id)) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized: You can only update your own courses"
            ]);
            exit();
        }

        // ✅ Admin can modify admin_action; Institute cannot
        $admin_action = ($role === 'admin') 
            ? trim($data['admin_action'] ?? $current_admin_action) 
            : $current_admin_action;

        // ✅ Update Query (16 fields)
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

        // ✅ Bind 16 parameters (16 placeholders, 16 variables)
        $stmt->bind_param(
            "sssdisssssissssi",
            $title,                  // 1 s
            $description,            // 2 s
            $duration,               // 3 s
            $fee,                    // 4 d
            $category_id,            // 5 i
            $tagged_skills,          // 6 s
            $batch_limit,            // 7 i
            $status,                 // 8 s
            $instructor_name,        // 9 s
            $mode,                   // 10 s
            $certification_allowed,  // 11 i
            $module_title,           // 12 s
            $module_description,     // 13 s
            $media,                  // 14 s
            $admin_action,           // 15 s
            $course_id               // 16 i
        );

        // ✅ Execute and Respond
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "✅ Course updated successfully!",
                "course_id" => $course_id,
                "updated_by" => $role
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "❌ Failed to update course",
                "error" => $stmt->error
            ]);
        }

        $stmt->close();
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
