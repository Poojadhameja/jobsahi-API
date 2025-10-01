<?php
require_once '../cors.php';

// Get logged-in user info
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']);
$user_id = intval($decoded['user_id']);
$user_role = $decoded['role']; // assume JWT returns role

try {
    // ✅ Fetch notifications where:
    // 1. receiver_id matches the logged-in user (notifications sent TO this user)
    // 2. received_role matches the user's role (role-based notifications)
    
    $sql = "SELECT 
                n.id, 
                n.receiver_id, 
                n.received_role,
                n.message, 
                n.type,
                n.is_read,
                n.created_at
            FROM notifications n
            WHERE n.receiver_id = ? AND n.received_role = ?
            ORDER BY n.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $user_role);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id'            => $row['id'],
            'receiver_id'   => $row['receiver_id'],
            'received_role' => $row['received_role'],
            'message'       => $row['message'],
            'type'          => $row['type'],
            'is_read'       => (int)$row['is_read'],
            'created_at'    => $row['created_at']
        ];
    }

    echo json_encode([
        "status" => true,
        "data" => $notifications,
        "message" => empty($notifications) ? "No notifications found" : "Notifications fetched successfully"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching notifications",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>