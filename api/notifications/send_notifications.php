<?php
require_once '../cors.php';

// Authenticate (allow all roles that can receive notifications)
$decoded = authenticateJWT(['admin', 'recruiter', 'institute']);
$user_id = intval($decoded['user_id']); // ✅ Logged-in user
$user_role = $decoded['role'] ?? null; // ✅ Get user's role from JWT

// Get raw POST body
$input = json_decode(file_get_contents("php://input"), true);

// Validate required input
if (!isset($input['message'])) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required field: message"
    ]);
    exit;
}

$message = trim($input['message']);
$type = isset($input['type']) ? trim($input['type']) : 'general'; // default type
$is_read = 0; // new notifications are unread
$received_role = $user_role; // ✅ Store the role of the user receiving the notification
$receiver_id = $user_id; // ✅ The user who will receive this notification

// Validate ENUM values (if you enforce them in DB)
$valid_types = ['general', 'system', 'reminder', 'alert'];
if ($type && !in_array($type, $valid_types)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid type. Must be one of: " . implode(', ', $valid_types)
    ]);
    exit;
}

try {
    // ✅ Insert notification with receiver_id and received_role (matching your table schema)
    $sql = "INSERT INTO notifications (receiver_id, received_role, message, type, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssi", $receiver_id, $received_role, $message, $type, $is_read);
    
    if ($stmt->execute()) {
        $notification_id = $conn->insert_id; // ✅ Get the auto-generated ID
        
        echo json_encode([
            "status" => true,
            "message" => "Notification created successfully",
            "data" => [
                "id"           => $notification_id, // ✅ Return the notification ID
                "receiver_id"  => $receiver_id,     // ✅ Changed from user_id
                "received_role"=> $received_role,   // ✅ Return received_role
                "message"      => $message,
                "type"         => $type,
                "is_read"      => $is_read,
                "created_at"   => date("Y-m-d H:i:s")
            ]
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to create notification"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error creating notification",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>