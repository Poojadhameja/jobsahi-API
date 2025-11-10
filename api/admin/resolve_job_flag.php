<?php
// resolve_job_flag.php - Resolve job flag (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT (admin only)
$decoded = authenticateJWT(['admin']);

try {
    // ✅ Get flag ID
    $flag_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($flag_id <= 0) {
        echo json_encode(["status" => false, "message" => "Invalid job flag ID"]);
        exit();
    }

    // ✅ Check if job flag exists and get job_id + current admin_action
    $check = $conn->prepare("SELECT id, job_id, admin_action FROM job_flags WHERE id = ?");
    $check->bind_param("i", $flag_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Job flag not found"]);
        exit();
    }

    $flag = $res->fetch_assoc();
    $job_id = intval($flag['job_id']);
    $current_action = $flag['admin_action']; // can be approved/rejected/pending

    // ✅ Step 1: Mark flag as reviewed but do not change admin_action
    $updateFlag = $conn->prepare("UPDATE job_flags SET reviewed = 1 WHERE id = ?");
    $updateFlag->bind_param("i", $flag_id);
    $updateFlag->execute();

    // ✅ Step 2: Approve the related job regardless of flag’s status
    if ($job_id > 0) {
        $updateJob = $conn->prepare("UPDATE jobs SET admin_action = 'approved' WHERE id = ?");
        $updateJob->bind_param("i", $job_id);
        $updateJob->execute();
    }

    // ✅ Step 3: Confirm result
    $confirm = $conn->prepare("SELECT id, job_id, reviewed, admin_action FROM job_flags WHERE id = ?");
    $confirm->bind_param("i", $flag_id);
    $confirm->execute();
    $updatedFlag = $confirm->get_result()->fetch_assoc();

    echo json_encode([
        "status" => true,
        "message" => "Job flag resolved successfully (job approved by admin)",
        "flag" => $updatedFlag,
        "job_id" => $job_id
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>
