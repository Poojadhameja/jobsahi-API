<?php
// get-notifications.php 
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

require_once '../db.php'; // DB connection

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