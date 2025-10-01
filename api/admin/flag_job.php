<?php
// flag_job.php - Flag a job posting
require_once '../cors.php';

// ✅ Authenticate JWT (allow all authenticated users)
$decoded = authenticateJWT(['admin']);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['job_id']) || empty($input['job_id'])) {
    echo json_encode([
        "status" => false,
        "message" => "Job ID is required"
    ]);
    exit;
}

if (!isset($input['reason']) || empty(trim($input['reason']))) {
    echo json_encode([
        "status" => false,
        "message" => "Reason for flagging is required"
    ]);
    exit;
}

$job_id = intval($input['job_id']);
$reason = trim($input['reason']);
$flagged_by = $decoded['user_id']; // Get user ID from JWT token

try {
    // Check if job exists
    $checkJob = $conn->prepare("SELECT id FROM jobs WHERE id = ?");
    $checkJob->bind_param("i", $job_id);
    $checkJob->execute();
    $jobResult = $checkJob->get_result();
    
    if ($jobResult->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Job posting not found"
        ]);
        exit;
    }
    
    // Check if user has already flagged this job
    $checkFlag = $conn->prepare("SELECT id FROM job_flags WHERE job_id = ? AND flagged_by = ?");
    $checkFlag->bind_param("ii", $job_id, $flagged_by);
    $checkFlag->execute();
    $flagResult = $checkFlag->get_result();
    
    if ($flagResult->num_rows > 0) {
        echo json_encode([
            "status" => false,
            "message" => "You have already flagged this job posting"
        ]);
        exit;
    }
    
    // Insert flag record
    $stmt = $conn->prepare("
        INSERT INTO job_flags (job_id, flagged_by, reason, reviewed, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->bind_param("iis", $job_id, $flagged_by, $reason);
    
    if ($stmt->execute()) {
        $flag_id = $stmt->insert_id;
        
        echo json_encode([
            "status" => true,
            "message" => "Job posting flagged successfully",
            "data" => [
                "flag_id" => $flag_id,
                "job_id" => $job_id,
                "flagged_by" => $flagged_by,
                "reason" => $reason,
                "reviewed" => "pending",
                "created_at" => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to flag job posting",
            "error" => $stmt->error
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>