<?php
require_once '../cors.php';

$decoded = authenticateJWT(['admin', 'institute']); 
$user_role = $decoded['role'] ?? '';  
$user_id   = $decoded['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!in_array($user_role, ['admin', 'institute'])) {
        echo json_encode(["status" => false, "message" => "Unauthorized"]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // ✅ Values fetch karna
    $title          = trim($data['title'] ?? '');
    $description    = trim($data['description'] ?? '');
    $duration       = trim($data['duration'] ?? '');
    $category_id    = !empty($data['category_id']) ? intval($data['category_id']) : null;
    $tagged_skills  = trim($data['tagged_skills'] ?? '');
    $batch_limit    = intval($data['batch_limit'] ?? 0);
    $status         = trim($data['status'] ?? 'Active');
    $instructor_name = trim($data['instructor_name'] ?? '');
    $mode           = trim($data['mode'] ?? 'Offline');
    $certification_allowed = isset($data['certification_allowed']) && $data['certification_allowed'] ? 1 : 0;
    $module_title   = trim($data['module_title'] ?? '');
    $module_description = trim($data['module_description'] ?? '');
    $media          = trim($data['media'] ?? '');
    $fee            = floatval($data['fee'] ?? 0);

    $admin_action = 'pending';
    $institute_id = ($user_role === 'institute') ? $user_id : 0;

    // ✅ Debug line (sirf testing ke liye)
    // file_put_contents('debug_log.txt', json_encode($data, JSON_PRETTY_PRINT));

    if (empty($title) || empty($description) || empty($duration) || $fee <= 0 || empty($instructor_name)) {
        echo json_encode(["status" => false, "message" => "All required fields must be filled"]);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO courses (
                institute_id, title, description, duration, category_id, tagged_skills, 
                batch_limit, status, instructor_name, mode, certification_allowed, 
                module_title, module_description, media, fee, admin_action
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // ✅ Type string exact 16 chars
        $stmt->bind_param(
            "isssisisssisssds",
            $institute_id,
            $title,
            $description,
            $duration,
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
            $fee,
            $admin_action
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
                "message" => "Database insert failed",
                "error"   => $stmt->error
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
