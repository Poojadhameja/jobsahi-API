<?php
require_once '../cors.php';

// Authenticate and allow admin, recruiter, institute, and student roles
$decoded = authenticateJWT(['admin', 'student', 'recruiter', 'institute']);
$sender_id = intval($decoded['user_id']); // ✅ Sender is the logged-in user
$sender_role = $decoded['role']; // ✅ Get sender role from JWT

// Get raw POST body
$input = json_decode(file_get_contents("php://input"), true);

// Validate required input fields
if (!isset($input['receiver_id'], $input['message'])) {
    echo json_encode([
        "status" => false,
        "message" => "Missing required fields: receiver_id, message"
    ]);
    exit;
}

$receiver_id = intval($input['receiver_id']);
$message = trim($input['message']);

// Handle optional fields with defaults
$type = isset($input['type']) ? trim($input['type']) : 'text';
$attachment_url = isset($input['attachment_url']) ? trim($input['attachment_url']) : NULL;
$attachment_type = isset($input['attachment_type']) ? trim($input['attachment_type']) : NULL;
$receiver_role = isset($input['receiver_role']) ? trim($input['receiver_role']) : NULL;

// Validate ENUM values
$valid_types = ['text', 'file', 'system'];
$valid_attachment_types = ['image', 'pdf', 'doc', 'other'];
$valid_roles = ['student', 'recruiter', 'institute', 'admin'];

if (!in_array($type, $valid_types)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid type. Must be one of: " . implode(', ', $valid_types)
    ]);
    exit;
}

if ($attachment_type && !in_array($attachment_type, $valid_attachment_types)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid attachment_type. Must be one of: " . implode(', ', $valid_attachment_types)
    ]);
    exit;
}

if ($receiver_role && !in_array($receiver_role, $valid_roles)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid receiver_role. Must be one of: " . implode(', ', $valid_roles)
    ]);
    exit;
}

try {
    // If receiver_role is not provided, try to fetch it from the database
    if (!$receiver_role) {
        $role_query = "
            SELECT 'student' AS role FROM student_profiles WHERE id = ? 
            UNION ALL 
            SELECT 'recruiter' AS role FROM recruiter_profiles WHERE id = ? 
            UNION ALL 
            SELECT 'institute' AS role FROM institute_profiles WHERE id = ? 
            UNION ALL 
            SELECT 'admin' AS role FROM users WHERE id = ?
            LIMIT 1
        ";

        $role_stmt = $conn->prepare($role_query);
        $role_stmt->bind_param("iiii", $receiver_id, $receiver_id, $receiver_id, $receiver_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();

        if ($role_result->num_rows > 0) {
            $role_row = $role_result->fetch_assoc();
            $receiver_role = $role_row['role'];
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Receiver not found or invalid receiver_id"
            ]);
            exit;
        }
    }

    // Insert message with all required and optional fields
    $sql = "INSERT INTO messages 
            (sender_id, sender_role, receiver_id, receiver_role, message, attachment_url, attachment_type, type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    // ✅ FIXED bind_param types (was wrong before)
    $stmt->bind_param("isisssss",
        $sender_id,        // i = int
        $sender_role,      // s = string
        $receiver_id,      // i = int
        $receiver_role,    // s = string
        $message,          // s = string
        $attachment_url,   // s = string (nullable)
        $attachment_type,  // s = string (nullable)
        $type              // s = string
    );

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Message sent successfully",
            "data" => [
                "id" => $stmt->insert_id,
                "sender_id" => $sender_id,
                "sender_role" => $sender_role,
                "receiver_id" => $receiver_id,
                "receiver_role" => $receiver_role,
                "message" => $message,
                "attachment_url" => $attachment_url,
                "attachment_type" => $attachment_type,
                "type" => $type,
                "created_at" => date("Y-m-d H:i:s")
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
