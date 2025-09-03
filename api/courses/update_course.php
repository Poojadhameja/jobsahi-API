<?php
// update_course.php - Update existing course (Admin, Institute access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'institute']); // returns array

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

// ✅ Validation
if (empty($title) || empty($description) || empty($duration) || $fee <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "All fields are required"
    ]);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE courses SET title = ?, description = ?, duration = ?, fee = ? WHERE id = ?");
    $stmt->bind_param("sssdi", $title, $description, $duration, $fee, $course_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Course updated successfully",
                "course_id" => $course_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Course not found or no changes made"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update course",
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
?>
