<?php
// delete_job.php - Close/archive job posting (Admin access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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