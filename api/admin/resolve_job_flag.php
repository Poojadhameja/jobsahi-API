<?php
// resolve_job_flag.php - Resolve job flag (Admin access only)
require_once '../cors.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // Get the job flag ID from URL
    $flag_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($flag_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid job flag ID"
        ]);
        exit();
    }
    
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
    $reviewedColumn = in_array('reviewed', $jobFlagsColumns) ? 'reviewed' : 'NULL';
    $updatedAtColumn = in_array('updated_at', $jobFlagsColumns) ? 'updated_at' : 'NULL';
    
    // Check if the job flag exists
    $checkStmt = $conn->prepare("SELECT {$idColumn} FROM job_flags WHERE {$idColumn} = ?");
    $checkStmt->bind_param("i", $flag_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Job flag not found"
        ]);
        exit();
    }
    
    // Build the update query with correct column names
    $updateFields = [];
    $updateFields[] = "{$reviewedColumn} = 1";
    if ($updatedAtColumn !== 'NULL') {
        $updateFields[] = "{$updatedAtColumn} = NOW()";
    }
    
    $updateQuery = "UPDATE job_flags SET " . implode(', ', $updateFields) . " WHERE {$idColumn} = ?";
    $stmt = $conn->prepare($updateQuery);
    
    if ($stmt->bind_param("i", $flag_id) && $stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Job flag resolved successfully",
                "flag_id" => $flag_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "No changes made to job flag"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to resolve job flag",
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