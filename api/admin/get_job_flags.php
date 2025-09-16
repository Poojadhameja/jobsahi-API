<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // First, let's check what columns exist in job_flags table
    $checkJobFlags = $conn->query("DESCRIBE job_flags");
    
    if (!$checkJobFlags) {
        throw new Exception("Cannot access job_flags table structure");
    }
    
    // Get column names for job_flags table
    $jobFlagsColumns = [];
    while ($row = $checkJobFlags->fetch_assoc()) {
        $jobFlagsColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('flag_id', $jobFlagsColumns)) {
        $idColumn = 'flag_id';
    } elseif (in_array('id', $jobFlagsColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist in job_flags table based on the actual schema
    $jobIdColumn = in_array('job_id', $jobFlagsColumns) ? 'job_id' : 'NULL';
    $flaggedByColumn = in_array('flagged_by', $jobFlagsColumns) ? 'flagged_by' : 'NULL';
    $reasonColumn = in_array('reason', $jobFlagsColumns) ? 'reason' : 'NULL';
    $reviewedColumn = in_array('reviewed', $jobFlagsColumns) ? 'reviewed' : 'NULL';
    $createdAtColumn = in_array('created_at', $jobFlagsColumns) ? 'created_at' : 'NULL';
    
    // Build the query with correct column names matching the actual schema
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as id,
            {$jobIdColumn} as job_id,
            {$flaggedByColumn} as flagged_by,
            {$reasonColumn} as reason,
            {$reviewedColumn} as reviewed,
            {$createdAtColumn} as created_at
        FROM job_flags
        ORDER BY {$createdAtColumn} DESC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $jobFlags = [];
        
        while ($row = $result->fetch_assoc()) {
            $jobFlags[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Flagged job postings retrieved successfully",
            "data" => $jobFlags,
            "count" => count($jobFlags)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve flagged job postings",
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