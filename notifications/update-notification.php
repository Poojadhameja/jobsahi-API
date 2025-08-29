<?php
// update-notification.php - Mark a notification as read
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only PATCH requests are allowed"
    ]);
    exit;
}

// Get notification ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Notification ID is required"
    ]);
    exit;
}

$notification_id = intval($_GET['id']);

// ✅ Fixed query (removed updated_at)
$sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $notification_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => true,
            "message" => "Notification marked as read successfully"
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Notification not found"
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to update notification"
    ]);
}

$stmt->close();
$conn->close();
?>
