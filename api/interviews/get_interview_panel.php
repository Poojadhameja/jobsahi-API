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
    // ✅ Get recruiter_profile_id first if recruiter
    $recruiter_profile_id = null;
    if ($user_role === 'recruiter') {
        $rp = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rp->bind_param("i", $user_id);
        $rp->execute();
        $rp_res = $rp->get_result();
        
        if ($rp_res->num_rows > 0) {
            $recruiter_profile_id = intval($rp_res->fetch_assoc()['id']);
        } else {
            // If recruiter profile not found, return empty
            echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
            exit();
        }
    }

    // ✅ Build query with prepared statements
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
        WHERE 1 = 1
    ";

    $params = [];
    $types = "";

    // ✅ Apply filters
    if ($panel_id > 0) {
        $sql .= " AND ip.id = ?";
        $params[] = $panel_id;
        $types .= "i";
    }
    if ($interview_id > 0) {
        $sql .= " AND ip.interview_id = ?";
        $params[] = $interview_id;
        $types .= "i";
    }

    // ✅ Restrict recruiter view (only their interviews)
    if ($user_role === 'recruiter' && $recruiter_profile_id) {
        // Get authorized interview IDs via applications -> jobs path
        // Note: interviews table does NOT have recruiter_id column
        $authCheck = $conn->prepare("
            SELECT DISTINCT i.id
            FROM interviews i
            INNER JOIN applications a ON i.application_id = a.id
            INNER JOIN jobs j ON a.job_id = j.id
            WHERE j.recruiter_id = ? AND i.application_id IS NOT NULL
        ");
        $authCheck->bind_param("i", $recruiter_profile_id);
        $authCheck->execute();
        $authResult = $authCheck->get_result();
        
        $authorizedInterviewIds = [];
        while ($row = $authResult->fetch_assoc()) {
            $authorizedInterviewIds[] = intval($row['id']);
        }
        
        if (empty($authorizedInterviewIds)) {
            // No authorized interviews, return empty
            echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
            exit();
        }
        
        // Add IN clause for authorized interview IDs
        $placeholders = implode(',', array_fill(0, count($authorizedInterviewIds), '?'));
        $sql .= " AND ip.interview_id IN ($placeholders)";
        $params = array_merge($params, $authorizedInterviewIds);
        $types .= str_repeat("i", count($authorizedInterviewIds));
    }

    $sql .= " ORDER BY ip.created_at DESC";

    // Execute with prepared statement if params exist
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $panelists = [];
    while ($row = $result->fetch_assoc()) {
        $panelists[] = $row;
    }

    if (empty($panelists)) {
        echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
    } else {
        echo json_encode(["status" => true, "message" => "Data fetched successfully", "data" => $panelists]);
    }

} catch (Throwable $e) {
    error_log("Get Interview Panel Error: " . $e->getMessage());
    echo json_encode(["status" => false, "message" => "Server error: " . $e->getMessage()]);
}

$conn->close();
?>

