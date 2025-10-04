<?php
// get_notification_templates.php - Get all notification templates (JWT required)
require_once '../cors.php';

// ✅ Authenticate JWT (any valid user can access notification templates)
$decoded = authenticateJWT(); // returns array
$user_role = $decoded['role']; // current logged-in user role

try {
    // Fetch all templates from notifications_templates table
    $sql = "
        SELECT 
            id,
            name,
            type,
            subject,
            body,
            created_at
        FROM notifications_templates
        ORDER BY created_at DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Error fetching notification templates: " . $conn->error);
    }

    $notificationTemplates = [];
    while ($row = $result->fetch_assoc()) {
        $notificationTemplates[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Notification templates retrieved successfully",
        "data" => $notificationTemplates,
        "count" => count($notificationTemplates)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
