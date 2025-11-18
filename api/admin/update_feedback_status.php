<?php
// update_feedback_status.php - Admin can update feedback status (PUT only)
require_once '../cors.php';
require_once '../db.php';

// Detect HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// ✅ Allow only PUT method
if ($method !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only PUT method is allowed"
    ]);
    exit();
}

// ✅ Authenticate (only admin can update feedback status)
$decoded = authenticateJWT(['admin']);

// ✅ Parse body
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['feedback_id'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "feedback_id is required"
    ]);
    exit();
}

$feedback_id = intval($data['feedback_id']);
$admin_action = isset($data['admin_action']) ? trim($data['admin_action']) : null;

// ✅ Validate admin_action
if (!$admin_action || !in_array($admin_action, ['pending', 'approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "admin_action must be one of: pending, approved, rejected"
    ]);
    exit();
}

// ✅ Check if feedback exists
$check_stmt = $conn->prepare("SELECT id FROM student_feedback WHERE id = ?");
$check_stmt->bind_param("i", $feedback_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "Feedback not found"
    ]);
    exit();
}
$check_stmt->close();

// ✅ Update feedback status
$update_sql = "UPDATE student_feedback SET admin_action = ?, updated_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("si", $admin_action, $feedback_id);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Feedback status updated successfully",
        "feedback_id" => $feedback_id,
        "admin_action" => $admin_action
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to update feedback status",
        "error" => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>

