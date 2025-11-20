<?php
// resolve_job_flag.php - Resolve job flag (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// Authenticate admin
$decoded = authenticateJWT(['admin']);

try {
    $job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($job_id <= 0) {
        echo json_encode(["status" => false, "message" => "Invalid job ID"]);
        exit();
    }

    // Check if flag exists
    $check = $conn->prepare("
        SELECT id, job_id, reviewed, admin_action 
        FROM job_flags 
        WHERE job_id = ? LIMIT 1
    ");
    $check->bind_param("i", $job_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "No flag found for this job"]);
        exit();
    }

    $flag = $res->fetch_assoc();
    $flag_id = intval($flag['id']);

    /* ----------------------------------------
       STEP 1: UPDATE job_flags table
    ---------------------------------------- */
    $updateFlag = $conn->prepare("
        UPDATE job_flags 
        SET reviewed = 1, admin_action = 'approved' 
        WHERE id = ?
    ");
    $updateFlag->bind_param("i", $flag_id);
    $updateFlag->execute();


    /* ----------------------------------------
       STEP 2: UPDATE jobs table
    ---------------------------------------- */
    $updateJob = $conn->prepare("
        UPDATE jobs 
        SET admin_action = 'approved'
        WHERE id = ?
    ");
    $updateJob->bind_param("i", $job_id);
    $updateJob->execute();


    /* ----------------------------------------
       STEP 3: FETCH UPDATED FLAG + JOB
    ---------------------------------------- */
    $flagQuery = $conn->prepare("
        SELECT id, job_id, reviewed, admin_action
        FROM job_flags
        WHERE id = ?
    ");
    $flagQuery->bind_param("i", $flag_id);
    $flagQuery->execute();
    $finalFlag = $flagQuery->get_result()->fetch_assoc();


    $jobQuery = $conn->prepare("
        SELECT id, title, admin_action 
        FROM jobs 
        WHERE id = ?
    ");
    $jobQuery->bind_param("i", $job_id);
    $jobQuery->execute();
    $finalJob = $jobQuery->get_result()->fetch_assoc();


    /* ----------------------------------------
       RESPONSE
    ---------------------------------------- */
    echo json_encode([
        "status" => true,
        "message" => "Job flag resolved & job approved successfully",
        "flag" => $finalFlag,
        "job" => $finalJob
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>
