<?php
// update_course.php - Update existing course with role-based visibility
require_once '../cors.php';

// Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'institute']); // returns array with 'role' key
$role = $decoded['role'] ?? '';

// Get course ID from URL parameter
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($course_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid course ID"
    ]);
    exit();
}

// Get PUT data
$data = json_decode(file_get_contents("php://input"), true);
$title       = isset($data['title']) ? trim($data['title']) : '';
$description = isset($data['description']) ? trim($data['description']) : '';
$duration    = isset($data['duration']) ? trim($data['duration']) : '';
$fee         = isset($data['fee']) ? floatval($data['fee']) : 0;

// Validation
if (empty($title) || empty($description) || empty($duration) || $fee <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "All fields are required"
    ]);
    exit();
}

try {
    // First, check if the course exists
    $check = $conn->prepare("SELECT admin_action FROM courses WHERE id = ?");
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

    // Decide admin_action update based on role
    if ($role === 'admin') {
        // Admin can override admin_action
        $admin_action = isset($data['admin_action']) ? trim($data['admin_action']) : $current_admin_action;
    } else {
        // Institute cannot modify admin_action, keep existing value
        $admin_action = $current_admin_action;
    }

    // Prepare update query
    $stmt = $conn->prepare("
        UPDATE courses 
        SET title = ?, description = ?, duration = ?, fee = ?, admin_action = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("sssdsi", $title, $description, $duration, $fee, $admin_action, $course_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => true,
            "message" => "Course updated successfully",
            "course_id" => $course_id,
            "updated_by" => $role
        ]);
    } else {
        echo json_encode([
            "status" => true,
            "message" => "No changes made, values are same as before",
            "course_id" => $course_id,
            "updated_by" => $role
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
