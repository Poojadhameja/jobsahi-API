<?php
// get_interview_panel.php - Fetch all or specific interview panel feedbacks
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate for multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 
$user_id = $decoded['user_id'];
$user_role = strtolower($decoded['role'] ?? '');

// ✅ Read optional filters
$panel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$interview_id = isset($_GET['interview_id']) ? intval($_GET['interview_id']) : 0;

try {

    // -------------------------------------------------------------
    // BASE QUERY
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // FILTERS (panel_id / interview_id)
    // -------------------------------------------------------------
    if ($panel_id > 0) {
        $sql .= " AND ip.id = $panel_id";
    }

    if ($interview_id > 0) {
        $sql .= " AND ip.interview_id = $interview_id";
    }

    // -------------------------------------------------------------
    // RECRUITER FILTER (THE MAIN FIX 🚀)
    // -------------------------------------------------------------
    if ($user_role === 'recruiter') {

        // Step 1: Get recruiter_profile_id from users.id
        $rec = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rec->bind_param("i", $user_id);
        $rec->execute();
        $rec_res = $rec->get_result();

        if ($rec_res->num_rows === 0) {
            echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
            exit;
        }

        $rec_row = $rec_res->fetch_assoc();
        $recruiter_profile_id = intval($rec_row['id']);

        // Step 2: Filter interview panel by recruiter-owned interviews
        $sql .= " AND i.id IN (
            SELECT i2.id 
            FROM interviews i2
            JOIN applications a2 ON i2.application_id = a2.id
            JOIN jobs j2 ON a2.job_id = j2.id
            WHERE j2.recruiter_id = $recruiter_profile_id
        )";
    }

    $sql .= " ORDER BY ip.created_at DESC";

    // -------------------------------------------------------------
    // EXECUTE FINAL QUERY
    // -------------------------------------------------------------
    $result = $conn->query($sql);

    $panelists = [];
    while ($row = $result->fetch_assoc()) {
        $panelists[] = $row;
    }

    // -------------------------------------------------------------
    // RESPONSE
    // -------------------------------------------------------------
    if (empty($panelists)) {
        echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
    } else {
        echo json_encode(["status" => true, "message" => "Data fetched successfully", "data" => $panelists]);
    }

} catch (Throwable $e) {
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
