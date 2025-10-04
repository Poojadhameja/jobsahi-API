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

    // Check if the job flag exists
    $checkStmt = $conn->prepare("SELECT id FROM job_flags WHERE id = ?");
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

    // ✅ Update reviewed + admin_action
    $updateQuery = "UPDATE job_flags 
                    SET reviewed = 1, admin_action = 'resolved' 
                    WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $flag_id);
    $stmt->execute();

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
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
