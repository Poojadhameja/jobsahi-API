<?php
include '../CORS.php';
// TEMPORARY: Test if file is accessible
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        "status" => true,
        "message" => "File is accessible! Use PATCH method with proper authentication."
    ]);
    exit;
}

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
require_once '../db.php';

// Authenticate JWT for both admin and student roles
authenticateJWT(['admin', 'recruiter','institute' , 'student']);

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

// First check if notification exists
$check_sql = "SELECT id FROM notifications WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $conn->error
    ]);
    exit;
}

$check_stmt->bind_param("i", $notification_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => false,
        "message" => "Notification with ID $notification_id not found"
    ]);
    $check_stmt->close();
    exit;
}

$check_stmt->close();

// Update the notification
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
    echo json_encode([
        "status" => true,
        "message" => "Notification marked as read successfully"
    ]);
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