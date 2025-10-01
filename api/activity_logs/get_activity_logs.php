<?php
<<<<<<< HEAD
// get_activity_logs.php - Get all system activity logs (JWT required)
require_once '../cors.php';
=======
require '../cors.php';
>>>>>>> fdb6ce0277ac46e48dd041ab5ec6de47b5826ee1

// ✅ Authenticate JWT (any valid user can access activity logs)
$decoded = authenticateJWT(['admin','student','institute','recruiter']); // returns array

try {
    // First, let's check what columns exist in activity_logs table
    $checkActivityLogs = $conn->query("DESCRIBE activity_logs");
    
    if (!$checkActivityLogs) {
        throw new Exception("Cannot access activity_logs table structure");
    }
    
    // Get column names for activity_logs table
    $activityLogsColumns = [];
    while ($row = $checkActivityLogs->fetch_assoc()) {
        $activityLogsColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('log_id', $activityLogsColumns)) {
        $idColumn = 'log_id';
    } elseif (in_array('id', $activityLogsColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist in activity_logs table based on the actual schema
    $userIdColumn = in_array('user_id', $activityLogsColumns) ? 'user_id' : 'NULL';
    $actionColumn = in_array('action', $activityLogsColumns) ? 'action' : 'NULL';
    $referenceTableColumn = in_array('reference_table', $activityLogsColumns) ? 'reference_table' : 'NULL';
    $referenceIdColumn = in_array('reference_id', $activityLogsColumns) ? 'reference_id' : 'NULL';
    $createdAtColumn = in_array('created_at', $activityLogsColumns) ? 'created_at' : 'NULL';
    
    // Build the query with correct column names matching the actual schema
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as id,
            {$userIdColumn} as user_id,
            {$actionColumn} as action,
            {$referenceTableColumn} as reference_table,
            {$referenceIdColumn} as reference_id,
            {$createdAtColumn} as created_at
        FROM activity_logs
        ORDER BY {$createdAtColumn} DESC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $activityLogs = [];
        
        while ($row = $result->fetch_assoc()) {
            $activityLogs[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Activity logs retrieved successfully",
            "data" => $activityLogs,
            "count" => count($activityLogs)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve activity logs",
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