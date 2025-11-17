<?php
// resolve_job_flag.php - Resolve job flag (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// Authenticate admin
$decoded = authenticateJWT(['admin']);

try {
    // id = job_id
    $job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($job_id <= 0) {
        echo json_encode(["status" => false, "message" => "Invalid job ID"]);
        exit();
    }

    // Check flag for this job
    $check = $conn->prepare("SELECT id, job_id, admin_action FROM job_flags WHERE job_id = ? LIMIT 1");
    $check->bind_param("i", $job_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "No flag found for this job"]);
        exit();
    }

    $flag = $res->fetch_assoc();
    $flag_id = intval($flag['id']);

    // Step 1: Mark flag as reviewed + approved
    $updateFlag = $conn->prepare("
        UPDATE job_flags 
        SET reviewed = 1, admin_action = 'approved' 
        WHERE id = ?
    ");
    $updateFlag->bind_param("i", $flag_id);
    $updateFlag->execute();

    // Step 2: Approve the job also
    $updateJob = $conn->prepare("
        UPDATE jobs 
        SET admin_action = 'approved' 
        WHERE id = ?
    ");
    $updateJob->bind_param("i", $job_id);
    $updateJob->execute();

    // Step 3: Return updated flag
    $confirm = $conn->prepare("SELECT id, job_id, reviewed, admin_action FROM job_flags WHERE id = ?");
    $confirm->bind_param("i", $flag_id);
    $confirm->execute();
    $updatedFlag = $confirm->get_result()->fetch_assoc();

    echo json_encode([
        "status" => true,
        "message" => "Job & flag approved successfully",
        "flag" => $updatedFlag,
        "job_id" => $job_id
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>
