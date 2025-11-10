<?php
require_once '../cors.php';

// Authenticate and allow both admin, recruiter, institute and student roles
$decoded = authenticateJWT(['admin', 'student', 'recruiter', 'institute']);
$current_user_id = intval($decoded['user_id']);
$current_user_role = $decoded['role'];

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

// Optional filters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$conversation_with = isset($_GET['conversation_with']) ? intval($_GET['conversation_with']) : null;
$message_type = isset($_GET['type']) ? trim($_GET['type']) : null;

// Validate message type if provided
$valid_types = ['text', 'file', 'system'];
if ($message_type && !in_array($message_type, $valid_types)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid type. Must be one of: " . implode(', ', $valid_types)
    ]);
    exit;
}

try {
    // Build the query based on user role and filters
    $where_conditions = [];
    $params = [];
    $param_types = "";

    // Role-based access control
    if ($current_user_role === 'admin') {
        // Admin can see all messages or filter by user_id
        if ($user_id) {
            $where_conditions[] = "receiver_id = ?";
            $params[] = $user_id;
            $param_types .= "i";
        }
    } else {
        // Non-admin users can only see their own messages (sent or received)
        if ($conversation_with) {
            // Get conversation between current user and specific user
            $where_conditions[] = "((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))";
            $params[] = $current_user_id;
            $params[] = $conversation_with;
            $params[] = $conversation_with;
            $params[] = $current_user_id;
            $param_types .= "iiii";
        } else {
            // Get all messages for current user (sent or received)
            $where_conditions[] = "(sender_id = ? OR receiver_id = ?)";
            $params[] = $current_user_id;
            $params[] = $current_user_id;
            $param_types .= "ii";
        }
    }

    // Add message type filter if specified
    if ($message_type) {
        $where_conditions[] = "type = ?";
        $params[] = $message_type;
        $param_types .= "s";
    }

    // Build the complete SQL query
    $sql = "SELECT 
                id,
                sender_id,
                sender_role,
                receiver_id,
                receiver_role,
                message,
                attachment_url,
                attachment_type,
                type,
                created_at
            FROM messages";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Add some additional useful information
        $message_data = [
            "id" => intval($row['id']),
            "sender_id" => intval($row['sender_id']),
            "sender_role" => $row['sender_role'],
            "receiver_id" => intval($row['receiver_id']),
            "receiver_role" => $row['receiver_role'],
            "message" => $row['message'],
            "attachment_url" => $row['attachment_url'],
            "attachment_type" => $row['attachment_type'],
            "type" => $row['type'],
            "created_at" => $row['created_at'],
            "is_sender" => ($row['sender_id'] == $current_user_id), // Helper field to identify if current user is sender
            "formatted_date" => date('M j, Y g:i A', strtotime($row['created_at'])) // Formatted date for display
        ];
        
        $messages[] = $message_data;
    }

    // Response with additional metadata
    $response = [
        "status" => true,
        "data" => $messages,
        "meta" => [
            "total_messages" => count($messages),
            "current_user_id" => $current_user_id,
            "current_user_role" => $current_user_role,
            "filters_applied" => [
                "user_id" => $user_id,
                "conversation_with" => $conversation_with,
                "message_type" => $message_type
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching messages data",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>