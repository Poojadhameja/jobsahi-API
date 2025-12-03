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
        $rp->close();
    }

    // ✅ Get student_profile_id if student role
    $student_profile_id = null;
    if ($user_role === 'student') {
        $sp = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
        $sp->bind_param("i", $user_id);
        $sp->execute();
        $sp_res = $sp->get_result();
        
        if ($sp_res->num_rows > 0) {
            $student_profile_id = intval($sp_res->fetch_assoc()['id']);
        } else {
            // If student profile not found, return empty
            echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
            exit();
        }
        $sp->close();
    }

    // ✅ Build query with prepared statements
    // Flow: interview_id -> application_id -> job_id -> job title
    $sql = "
        SELECT 
            ip.id,
            ip.interview_id,
            ip.panelist_name,
            ip.feedback,
            ip.rating,
            ip.created_at,
            ip.admin_action,
            COALESCE(j.title, 'N/A') AS job_title
        FROM interview_panel ip
        INNER JOIN interviews i ON ip.interview_id = i.id
        LEFT JOIN applications a ON i.application_id = a.id
        LEFT JOIN jobs j ON a.job_id = j.id
        WHERE 1 = 1
          AND (i.deleted_at IS NULL OR i.deleted_at = '0000-00-00 00:00:00')
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
        // Only interviews for jobs owned by this recruiter
        $authCheck = $conn->prepare("
            SELECT DISTINCT i.id
            FROM interviews i
            INNER JOIN applications a ON i.application_id = a.id
            INNER JOIN jobs j ON a.job_id = j.id
            WHERE j.recruiter_id = ? 
              AND i.application_id IS NOT NULL
              AND (i.deleted_at IS NULL OR i.deleted_at = '0000-00-00 00:00:00')
              AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
        ");
        $authCheck->bind_param("i", $recruiter_profile_id);
        $authCheck->execute();
        $authResult = $authCheck->get_result();
        
        $authorizedInterviewIds = [];
        while ($row = $authResult->fetch_assoc()) {
            $authorizedInterviewIds[] = intval($row['id']);
        }
        $authCheck->close();
        
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

    // ✅ Restrict student view (only their own interviews)
    if ($user_role === 'student' && $student_profile_id) {
        // Get authorized interview IDs via applications -> student path
        $authCheck = $conn->prepare("
            SELECT DISTINCT i.id
            FROM interviews i
            INNER JOIN applications a ON i.application_id = a.id
            WHERE a.student_id = ? AND i.application_id IS NOT NULL
        ");
        $authCheck->bind_param("i", $student_profile_id);
        $authCheck->execute();
        $authResult = $authCheck->get_result();
        
        $authorizedInterviewIds = [];
        while ($row = $authResult->fetch_assoc()) {
            $authorizedInterviewIds[] = intval($row['id']);
        }
        $authCheck->close();
        
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
        if (!$stmt) {
            throw new Exception("Prepare error: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute error: " . $stmt->error);
        }
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query error: " . $conn->error);
        }
    }

    $panelists = [];
    while ($row = $result->fetch_assoc()) {
        // Get job_title - check multiple possible field names
        $job_title = 'N/A';
        if (isset($row['job_title']) && !empty($row['job_title'])) {
            $job_title = $row['job_title'];
        } elseif (isset($row['title']) && !empty($row['title'])) {
            $job_title = $row['title'];
        }
        
        $panelists[] = [
            "id" => intval($row['id'] ?? 0),
            "interview_id" => intval($row['interview_id'] ?? 0),
            "panelist_name" => $row['panelist_name'] ?? '',
            "feedback" => $row['feedback'] ?? '',
            "rating" => intval($row['rating'] ?? 0),
            "created_at" => $row['created_at'] ?? '',
            "admin_action" => $row['admin_action'] ?? '',
            "job_title" => $job_title
        ];
    }

    if (empty($panelists)) {
        echo json_encode(["status" => true, "message" => "No records found", "data" => []]);
    } else {
        echo json_encode(["status" => true, "message" => "Data fetched successfully", "data" => $panelists]);
    }
    
    // Close statement if it was prepared
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }

} catch (Throwable $e) {
    error_log("Get Interview Panel Error: " . $e->getMessage());
    echo json_encode(["status" => false, "message" => "Server error: " . $e->getMessage()]);
}

$conn->close();
?>