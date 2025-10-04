<?php
require_once '../cors.php';

// Authenticate (allow all roles that can receive notifications)
$decoded = authenticateJWT(['admin', 'recruiter', 'institute']);
$sender_id = intval($decoded['user_id']); // ✅ The user creating the notification
$sender_role = $decoded['role'] ?? null; // ✅ Get sender's role from JWT

// Get raw POST body
$input = json_decode(file_get_contents("php://input"), true);

// Validate required input
if (!isset($input['message']) || !isset($input['receiver_id']) || !isset($input['receiver_role'])) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required fields: message, receiver_id, receiver_role"
    ]);
    exit;
}

$message = trim($input['message']);
$type = isset($input['type']) ? trim($input['type']) : 'general'; // default type
$is_read = 0; // new notifications are unread
$receiver_role = trim($input['receiver_role']); // ✅ Store the role of the user receiving the notification
$receiver_id = intval($input['receiver_id']); // ✅ The user who will receive this notification

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
    // ✅ Insert notification with sender_id, receiver_id and receiver_role
    $sql = "INSERT INTO notifications (user_id, receiver_id, receiver_role, message, type, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssi", $sender_id, $receiver_id, $receiver_role, $message, $type, $is_read);
    
    if ($stmt->execute()) {
        $notification_id = $conn->insert_id; // ✅ Get the auto-generated ID
        
        echo json_encode([
            "status" => true,
            "message" => "Notification created successfully",
            "data" => [
                "id"            => $notification_id,
                "user_id"     => $sender_id,      // ✅ Who sent it
                "receiver_id"   => $receiver_id,    // ✅ Who receives it
                "receiver_role" => $receiver_role,  // ✅ Receiver's role
                "message"       => $message,
                "type"          => $type,
                "is_read"       => $is_read,
                "created_at"    => date("Y-m-d H:i:s")
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