<?php
require_once '../cors.php';

// Authenticate and allow admin, recruiter, institute, and student roles
$decoded = authenticateJWT(['admin', 'student', 'recruiter', 'institute']);
$sender_id = intval($decoded['user_id']); // ✅ Sender is the logged-in user

// Get raw POST body
$input = json_decode(file_get_contents("php://input"), true);

// Validate input (no need for sender_id here)
if (!isset($input['receiver_id'], $input['message'], $input['type'])) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required fields: receiver_id, message, type"
    ]);
    exit;
}

$receiver_id = intval($input['receiver_id']);
$message     = trim($input['message']);
$type        = trim($input['type']); // e.g., 'text', 'image', etc.

try {
    $sql = "INSERT INTO messages (sender_id, receiver_id, message, type, created_at)
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $sender_id, $receiver_id, $message, $type);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Message sent successfully",
            "data" => [
                "id"          => $stmt->insert_id,
                "sender_id"   => $sender_id,
                "receiver_id" => $receiver_id,
                "message"     => $message,
                "type"        => $type,
                "created_at"  => date("Y-m-d H:i:s")
            ]
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to send message"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error sending message",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
