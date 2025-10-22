<?php
// create_course.php - Create new course (Admin or Institute access)
require_once '../cors.php';

// Authenticate JWT and allow only admin or institute
$decoded = authenticateJWT(['admin', 'institute']); 
$user_role = $decoded['role'] ?? '';  
$user_id   = $decoded['user_id'] ?? 0;

// ---------- POST: Create New Course ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!in_array($user_role, ['admin', 'institute'])) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: Only admin or institute can create courses"
        ]);
        exit();
    }

    // Parse JSON from frontend
    $data = json_decode(file_get_contents("php://input"), true);

    // Collect all input fields
    $title          = trim($data['title'] ?? '');
    $description    = trim(strip_tags($data['description'] ?? ''));
    $duration       = trim($data['duration'] ?? '');
    $category_id    = intval($data['category_id'] ?? 0);
    $tagged_skills  = trim($data['tagged_skills'] ?? '');
    $batch_limit    = intval($data['batch_limit'] ?? 0);
    $status         = trim($data['status'] ?? 'active');
    $instructor_name = trim($data['instructor_name'] ?? '');
    $mode           = trim($data['mode'] ?? 'offline');
    $certification_allowed = isset($data['certification_allowed']) && $data['certification_allowed'] ? 1 : 0;
    $module_title   = trim($data['module_title'] ?? '');
    $module_description = trim($data['module_description'] ?? '');
    $media          = trim($data['media'] ?? '');
    $fee            = floatval($data['fee'] ?? 0);

    // Role-based assignment
    $admin_action = 'pending';
    $institute_id = ($user_role === 'institute') ? $user_id : 0;

    // Basic validation
    if (
        empty($title) || empty($description) || empty($duration) || 
        $fee <= 0 || empty($instructor_name)
    ) {
        echo json_encode([
            "status" => false,
            "message" => "All required fields must be filled properly."
        ]);
        exit();
    }

    try {
        // ✅ Insert query with all fields
        $stmt = $conn->prepare("
            INSERT INTO courses (
                institute_id, title, description, duration, category_id, tagged_skills, 
                batch_limit, status, instructor_name, mode, certification_allowed, 
                module_title, module_description, media, fee, admin_action
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssisssssssssdss",
            $institute_id, $title, $description, $duration, $category_id, $tagged_skills,
            $batch_limit, $status, $instructor_name, $mode, $certification_allowed,
            $module_title, $module_description, $media, $fee, $admin_action
        );

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
                "error"   => $stmt->error
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
