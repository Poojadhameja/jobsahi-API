<?php
// resolve_job_flag.php - Resolve job flag (Admin access only)
require_once '../cors.php';
require_once '../db.php';

// Only Admin
$decoded = authenticateJWT(['admin']);

try {

    $flag_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($flag_id <= 0) {
        echo json_encode(["status" => false, "message" => "Invalid job flag ID"]);
        exit();
    }

    // Fetch flag (including existing admin_action)
    $stmt = $conn->prepare("
        SELECT id, job_id, admin_action 
        FROM job_flags 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $flag_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Job flag not found"]);
        exit();
    }

    $flag = $res->fetch_assoc();
    $job_id = intval($flag['job_id']);
    $current_action = strtolower(trim($flag['admin_action']));

    // VALID ACTIONS
    $valid = ['approved', 'rejected', 'pending'];
    if (!in_array($current_action, $valid)) {
        $current_action = 'pending';
    }

    // ---------------------------------------------------
    // ⭐ ADMIN LOGIC (NO URL INPUT)
    // ---------------------------------------------------
    // pending → approved
    // approved → keep approved
    // rejected → keep rejected
    if ($current_action === 'pending') {
        $new_action = 'approved';
    } else {
        $new_action = $current_action; // keep as is
    }

    // Step 1: Update job_flags
    $updateFlag = $conn->prepare("
        UPDATE job_flags 
        SET reviewed = 1, admin_action = ? 
        WHERE id = ?
    ");
    $updateFlag->bind_param("si", $new_action, $flag_id);
    $updateFlag->execute();

    // Step 2: Update job status
    if ($job_id > 0) {
        $updateJob = $conn->prepare("
            UPDATE jobs 
            SET admin_action = ? 
            WHERE id = ?
        ");
        $updateJob->bind_param("si", $new_action, $job_id);
        $updateJob->execute();
    }

    // FINAL SHORT OUTPUT
    echo json_encode([
        "status"        => true,
        "message"       => "Job flag resolved successfully",
        "flag_id"       => $flag_id,
        "admin_action"  => $new_action
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>
