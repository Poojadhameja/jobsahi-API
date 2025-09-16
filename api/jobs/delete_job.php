<?php
include '../CORS.php';
require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin']); // returns array

// Get job ID from URL parameter
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid job ID"
    ]);
    exit();
}

try {
    // Update job status to 'closed' or 'archived' instead of deleting
    $stmt = $conn->prepare("UPDATE jobs SET status = 'closed' WHERE id = ?");
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Job closed/archived successfully",
                "job_id" => $job_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Job not found or already closed"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to close job",
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