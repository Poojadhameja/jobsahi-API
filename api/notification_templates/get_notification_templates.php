<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
// ✅ Authenticate JWT (any valid user can access notification templates)
$decoded = authenticateJWT(); // returns array
try {
    // First, let's check what columns exist in notifications_templates table
    $checkNotificationTemplates = $conn->query("DESCRIBE notifications_templates");
    
    if (!$checkNotificationTemplates) {
        throw new Exception("Cannot access notifications_templates table structure");
    }
    
    // Get column names for notifications_templates table
    $notificationTemplatesColumns = [];
    while ($row = $checkNotificationTemplates->fetch_assoc()) {
        $notificationTemplatesColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('template_id', $notificationTemplatesColumns)) {
        $idColumn = 'template_id';
    } elseif (in_array('id', $notificationTemplatesColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist in notifications_templates table based on the actual schema
    $nameColumn = in_array('name', $notificationTemplatesColumns) ? 'name' : 'NULL';
    $typeColumn = in_array('type', $notificationTemplatesColumns) ? 'type' : 'NULL';
    $subjectColumn = in_array('subject', $notificationTemplatesColumns) ? 'subject' : 'NULL';
    $bodyColumn = in_array('body', $notificationTemplatesColumns) ? 'body' : 'NULL';
    $createdAtColumn = in_array('created_at', $notificationTemplatesColumns) ? 'created_at' : 'NULL';
    
    // Build the query with correct column names matching the actual schema
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as id,
            {$nameColumn} as name,
            {$typeColumn} as type,
            {$subjectColumn} as subject,
            {$bodyColumn} as body,
            {$createdAtColumn} as created_at
        FROM notifications_templates
        ORDER BY {$createdAtColumn} DESC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
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
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve notification templates",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
$conn->close();
?>