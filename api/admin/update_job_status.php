<?php
// update_job_admin_action.php - Update only admin_action (Admin access only)
require_once '../cors.php';

// Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // Get job ID from URL parameter
    $job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($job_id <= 0) {
        throw new Exception("Invalid job ID provided");
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['admin_action'])) {
        throw new Exception("admin_action is required");
    }

    $admin_action = trim($input['admin_action']);
    
    // Optional: validate allowed admin_action values
    $allowed_actions = ['approved', 'rejected', 'pending']; // customize as needed
    if (!in_array($admin_action, $allowed_actions)) {
        throw new Exception("Invalid admin_action. Allowed values: " . implode(', ', $allowed_actions));
    }

    // Check if job exists
    $checkStmt = $conn->prepare("SELECT id FROM jobs WHERE id = ?");
    $checkStmt->bind_param("i", $job_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        throw new Exception("Job not found");
    }

    // Update only admin_action
    $stmt = $conn->prepare("UPDATE jobs SET admin_action = ? WHERE id = ?");
    $stmt->bind_param("si", $admin_action, $job_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Return updated job details
            $selectStmt = $conn->prepare("SELECT id as job_id, admin_action FROM jobs WHERE id = ?");
            $selectStmt->bind_param("i", $job_id);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $updatedJob = $result->fetch_assoc();

            echo json_encode([
                "status" => true,
                "message" => "admin_action updated successfully",
                "data" => $updatedJob
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "No changes made to admin_action"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update admin_action",
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
