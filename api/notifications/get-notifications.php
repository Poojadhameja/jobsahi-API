<?php
// get-notifications.php 
require_once '../cors.php';

// Authenticate and allow both admin and student roles
authenticateJWT(['admin', 'recruiter','institute' , 'student']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

try {
    // Adjust columns based on your table structure
    $sql = "SELECT id, message, created_at, is_read 
            FROM notifications 
            ORDER BY created_at DESC";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }

        echo json_encode(["status" => true, "data" => $notifications]);
    } else {
        echo json_encode(["status" => true, "data" => [], "message" => "No notifications found"]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching notifications",
        "error" => $e->getMessage()
    ]);
}

$conn->close();

?>