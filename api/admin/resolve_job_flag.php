<?php
// resolve_job_flag.php - Resolve job flag (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// Authenticate admin
$decoded = authenticateJWT(['admin']);

try {
    // Get flag_id from URL parameter (primary key of job_flags table)
    $flag_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($flag_id <= 0) {
        echo json_encode(["status" => false, "message" => "Invalid flag ID"]);
        exit();
    }

    // Get admin_action from request body (PUT request)
    $input = json_decode(file_get_contents('php://input'), true);
    $admin_action = isset($input['admin_action']) ? trim($input['admin_action']) : 'approved';
    
    // Validate admin_action value
    $allowed_actions = ['approved', 'pending', 'flagged'];
    if (!in_array(strtolower($admin_action), $allowed_actions)) {
        $admin_action = 'approved'; // Default to approved if invalid
    }
    
    // Get reason from request body
    $reason = isset($input['reason']) ? trim($input['reason']) : '';

    // Check if flag exists
    $check = $conn->prepare("
        SELECT id, job_id, reviewed, admin_action 
        FROM job_flags 
        WHERE id = ? LIMIT 1
    ");
    $check->bind_param("i", $flag_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "No flag found with this ID"]);
        exit();
    }

    $flag = $res->fetch_assoc();
    $job_id = intval($flag['job_id']);

    // Determine reviewed status based on admin_action
    $reviewed = ($admin_action === 'approved') ? 1 : 0;

    /* ----------------------------------------
       STEP 1: UPDATE job_flags table
    ---------------------------------------- */
    $updateFlag = $conn->prepare("
        UPDATE job_flags 
        SET reviewed = ?, admin_action = ?, reason = ?
        WHERE id = ?
    ");
    $updateFlag->bind_param("issi", $reviewed, $admin_action, $reason, $flag_id);
    $updateFlag->execute();


    /* ----------------------------------------
       STEP 2: UPDATE jobs table
    ---------------------------------------- */
    $updateJob = $conn->prepare("
        UPDATE jobs 
        SET admin_action = ?
        WHERE id = ?
    ");
    $updateJob->bind_param("si", $admin_action, $job_id);
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
    // Dynamic message based on admin_action
    $message = "Job flag updated successfully";
    if ($admin_action === 'approved') {
        $message = "Job flag resolved & job approved successfully";
    } else if ($admin_action === 'pending') {
        $message = "Job re-flagged successfully";
    } else if ($admin_action === 'flagged') {
        $message = "Job flagged successfully";
    }

    echo json_encode([
        "status" => true,
        "message" => $message,
        "flag_id" => $flag_id,
        "admin_action" => $admin_action,
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
