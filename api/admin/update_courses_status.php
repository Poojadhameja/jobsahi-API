<?php
// update_course_admin_action.php - Update only course admin_action (Admin access only)
// PUT /api/v1/admin/courses/{id}/admin_action
require_once '../cors.php';

// Only allow PUT method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Method not allowed. Use PUT method."
    ]);
    exit;
}

// Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // Get course ID from URL parameter
    $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($course_id <= 0) {
        throw new Exception("Invalid course ID provided");
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['admin_action'])) {
        throw new Exception("admin_action is required");
    }

    $admin_action = trim($input['admin_action']);
    
    // Validate allowed admin_action values
    $allowed_actions = ['approved', 'rejected', 'pending']; // customize as needed
    if (!in_array($admin_action, $allowed_actions)) {
        throw new Exception("Invalid admin_action. Allowed values: " . implode(', ', $allowed_actions));
    }

    // Check if course exists and fetch current admin_action
    $checkStmt = $conn->prepare("SELECT id, title, admin_action FROM courses WHERE id = ?");
    $checkStmt->bind_param("i", $course_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        throw new Exception("Course not found");
    }

    $currentCourse = $checkResult->fetch_assoc();

    // If admin_action is already the same → return success
    if ($currentCourse['admin_action'] === $admin_action) {
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "Course admin_action is already set to '$admin_action'",
            "data" => [
                "course_id" => $currentCourse['id'],
                "title" => $currentCourse['title'],
                "admin_action" => $currentCourse['admin_action']
            ]
        ]);
        exit;
    }

    // Update admin_action
    $stmt = $conn->prepare("UPDATE courses SET admin_action = ? WHERE id = ?");
    $stmt->bind_param("si", $admin_action, $course_id);

    if ($stmt->execute()) {
        // Return updated course details
        $selectStmt = $conn->prepare("SELECT id as course_id, title, admin_action FROM courses WHERE id = ?");
        $selectStmt->bind_param("i", $course_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $updatedCourse = $result->fetch_assoc();

        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "Course admin_action updated successfully",
            "data" => $updatedCourse
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to update course admin_action",
            "error" => $stmt->error
        ]);
    }

} catch (Exception $e) {
    if (!headers_sent()) {
        http_response_code(400);
    }
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
