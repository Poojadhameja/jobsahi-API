<?php
require_once '../cors.php';
require_once '../db.php';

$decoded = authenticateJWT(['admin','recruiter','institute','student']);
$user_id = intval($decoded['user_id']);
$user_role = strtolower($decoded['role'] ?? '');

$panel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$interview_id = isset($_GET['interview_id']) ? intval($_GET['interview_id']) : 0;

try {

    // BASE QUERY
    $sql = "
        SELECT 
            ip.id,
            ip.interview_id,
            ip.panelist_name,
            ip.feedback,
            ip.rating,
            ip.created_at,
            ip.admin_action
        FROM interview_panel ip
        JOIN interviews i ON ip.interview_id = i.id
        WHERE 1
    ";

    if ($panel_id > 0) {
        $sql .= " AND ip.id = $panel_id";
    }

    if ($interview_id > 0) {
        $sql .= " AND ip.interview_id = $interview_id";
    }

    // ===============================
    // 🔐 RECRUITER FILTER (FINAL)
    // ===============================
    if ($user_role === 'recruiter') {

        // 1️⃣ GET recruiter_profile_id
        $rp = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rp->bind_param("i", $user_id);
        $rp->execute();
        $rp_result = $rp->get_result();

        if ($rp_result->num_rows === 0) {
            echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
            exit;
        }

        $recruiter_profile_id = intval($rp_result->fetch_assoc()['id']);

        // 2️⃣ ONLY VALID WAY (Matches YOUR Database)
        $sql .= "
            AND i.id IN (
                SELECT i2.id
                FROM interviews i2
                JOIN applications a2 ON i2.application_id = a2.id
                JOIN jobs j2 ON a2.job_id = j2.id
                WHERE j2.recruiter_id = $recruiter_profile_id
            )
        ";
    }

    $sql .= " ORDER BY ip.created_at DESC";

    // EXECUTE
    $result = $conn->query($sql);

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
    } else {
        echo json_encode(["status" => true, "message" => "Data fetched successfully", "data" => $rows]);
    }

} catch (Throwable $e) {

    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
