<?php
// send_notifications.php - Send broadcast/system notifications (JWT required)
require_once '../cors.php';

// ✅ Authenticate JWT (any valid user can send notifications)
$decoded = authenticateJWT(); // returns array

try {
    // First, let's check what columns exist in notifications table
    $checkNotifications = $conn->query("DESCRIBE notifications");
    
    if (!$checkNotifications) {
        throw new Exception("Cannot access notifications table structure");
    }
    
    // Check if notification_templates table exists (optional)
    $templatesExists = false;
    $checkTemplates = $conn->query("SHOW TABLES LIKE 'notification_templates'");
    if ($checkTemplates && $checkTemplates->num_rows > 0) {
        $templatesExists = true;
        $checkTemplates = $conn->query("DESCRIBE notification_templates");
    }
    
    // Get column names for notifications table
    $notificationsColumns = [];
    while ($row = $checkNotifications->fetch_assoc()) {
        $notificationsColumns[] = $row['Field'];
    }
    
    // Get column names for notification_templates table (if exists)
    $templatesColumns = [];
    if ($templatesExists && $checkTemplates) {
        while ($row = $checkTemplates->fetch_assoc()) {
            $templatesColumns[] = $row['Field'];
        }
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Determine column names for notifications table based on actual schema
    $idColumn = in_array('id', $notificationsColumns) ? 'id' : 'notification_id';
    $userIdColumn = in_array('user_id', $notificationsColumns) ? 'user_id' : 'NULL';
    $messageColumn = in_array('message', $notificationsColumns) ? 'message' : 'NULL';
    $typeColumn = in_array('type', $notificationsColumns) ? 'type' : 'NULL';
    $isReadColumn = in_array('is_read', $notificationsColumns) ? 'is_read' : 'NULL';
    $createdAtColumn = in_array('created_at', $notificationsColumns) ? 'created_at' : 'NULL';
    
    // Determine column names for notification_templates table based on actual schema (if exists)
    $templateNameColumn = 'NULL';
    $templateTypeColumn = 'NULL';
    $templateSubjectColumn = 'NULL';
    $templateBodyColumn = 'NULL';
    
    if ($templatesExists) {
        $templateNameColumn = in_array('name', $templatesColumns) ? 'name' : 'NULL';
        $templateTypeColumn = in_array('type', $templatesColumns) ? 'type' : 'NULL';
        $templateSubjectColumn = in_array('subject', $templatesColumns) ? 'subject' : 'NULL';
        $templateBodyColumn = in_array('body', $templatesColumns) ? 'body' : 'NULL';
    }
    
    // Extract data from input
    $message = $input['message'] ?? '';
    $type = $input['type'] ?? 'system';
    $recipients = $input['recipients'] ?? []; // array of user IDs, empty for broadcast
    $templateId = $input['template_id'] ?? null;
    $senderId = $decoded['user_id'] ?? $decoded['id'] ?? null;
    
    // If template_id is provided and templates table exists, get template data
    if ($templateId && $templatesExists) {
        $templateStmt = $conn->prepare("SELECT {$templateNameColumn} as name, {$templateTypeColumn} as type, {$templateSubjectColumn} as subject, {$templateBodyColumn} as body FROM notification_templates WHERE id = ?");
        $templateStmt->bind_param("i", $templateId);
        $templateStmt->execute();
        $templateResult = $templateStmt->get_result();
        
        if ($templateRow = $templateResult->fetch_assoc()) {
            $message = $templateRow['body'] ?: $message;
            $type = $templateRow['type'] ?: $type;
        }
        $templateStmt->close();
    } elseif ($templateId && !$templatesExists) {
        echo json_encode([
            "status" => false,
            "message" => "Template functionality not available - notification_templates table doesn't exist"
        ]);
        exit();
    }
    
    // Validate required fields
    if (empty($message)) {
        echo json_encode([
            "status" => false,
            "message" => "Message is required"
        ]);
        exit();
    }
    
    // Prepare notification insert query
    $insertColumns = [];
    $insertValues = [];
    $insertParams = [];
    $paramTypes = '';
    
    if ($userIdColumn != 'NULL') {
        $insertColumns[] = 'user_id';
        $insertValues[] = '?';
        $paramTypes .= 'i';
    }
    
    if ($messageColumn != 'NULL') {
        $insertColumns[] = 'message';
        $insertValues[] = '?';
        $insertParams[] = $message;
        $paramTypes .= 's';
    }
    
    if ($typeColumn != 'NULL') {
        $insertColumns[] = 'type';
        $insertValues[] = '?';
        $insertParams[] = $type;
        $paramTypes .= 's';
    }
    
    if ($isReadColumn != 'NULL') {
        $insertColumns[] = 'is_read';
        $insertValues[] = '?';
        $insertParams[] = 0;
        $paramTypes .= 'i';
    }
    
    if ($createdAtColumn != 'NULL') {
        $insertColumns[] = 'created_at';
        $insertValues[] = 'NOW()';
    }
    
    $insertSQL = "INSERT INTO notifications (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
    
    $conn->begin_transaction();
    $insertedCount = 0;
    
    try {
        if (empty($recipients)) {
            // Broadcast notification - send to all users
            // First check what columns exist in users table
            $checkUsers = $conn->query("DESCRIBE users");
            $userColumns = [];
            if ($checkUsers) {
                while ($row = $checkUsers->fetch_assoc()) {
                    $userColumns[] = $row['Field'];
                }
            }
            
            // Build WHERE clause based on available columns
            $whereClause = "";
            if (in_array('status', $userColumns)) {
                $whereClause = "WHERE status = 'active'";
            } elseif (in_array('is_active', $userColumns)) {
                $whereClause = "WHERE is_active = 1";
            } else {
                $whereClause = ""; // No status filtering if no status column exists
            }
            
            $usersStmt = $conn->prepare("SELECT id FROM users " . $whereClause);
            $usersStmt->execute();
            $usersResult = $usersStmt->get_result();
            
            while ($userRow = $usersResult->fetch_assoc()) {
                $currentParams = $insertParams;
                array_unshift($currentParams, $userRow['id']); // Add user_id as first parameter
                
                $stmt = $conn->prepare($insertSQL);
                if (!empty($currentParams)) {
                    $stmt->bind_param($paramTypes, ...$currentParams);
                }
                $stmt->execute();
                $insertedCount++;
                $stmt->close();
            }
            $usersStmt->close();
        } else {
            // Send to specific recipients
            foreach ($recipients as $recipientId) {
                $currentParams = $insertParams;
                array_unshift($currentParams, $recipientId); // Add user_id as first parameter
                
                $stmt = $conn->prepare($insertSQL);
                if (!empty($currentParams)) {
                    $stmt->bind_param($paramTypes, ...$currentParams);
                }
                $stmt->execute();
                $insertedCount++;
                $stmt->close();
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            "status" => true,
            "message" => "Notifications sent successfully",
            "data" => [
                "notifications_sent" => $insertedCount,
                "message" => $message,
                "type" => $type
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>