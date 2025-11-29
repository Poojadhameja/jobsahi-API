<?php
// get-notifications.php - Get notifications for logged-in user
// 
// ⚠️ IMPORTANT: Notification system is primarily for STUDENTS
// - Push notifications are sent only to students
// - Most notifications in database are for students
// - This endpoint can be accessed by all roles, but notifications are mainly student-focused
//
require_once '../cors.php';

// Get logged-in user info
$decoded = authenticateJWT(['admin', 'recruiter','institute' , 'student']);
$user_id = intval($decoded['user_id']);
$user_role = $decoded['role']; // assume JWT returns role

try {
    // Base query: notifications that should be visible to this user
    // ⚠️ Note: Most notifications are for students (received_role = 'student')
    // Assuming notifications table has: user_id (sender), message, type, is_read, created_at
    // And users table has: id, role
    $sql = "SELECT n.id, n.user_id as sender_id, u.role as sender_role, n.message, n.created_at, n.is_read
            FROM notifications n
            JOIN users u ON n.user_id = u.id
            WHERE
                (
                    (u.role = 'recruiter' AND ? IN ('admin','institute','student')) OR
                    (u.role = 'institute' AND ? = 'student') OR
                    (u.role = 'admin' AND ? IN ('institute','student'))
                )
            ORDER BY n.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $user_role, $user_role, $user_role);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    echo json_encode([
        "status" => true,
        "data" => $notifications,
        "message" => empty($notifications) ? "No notifications found" : ""
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
