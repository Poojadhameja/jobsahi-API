<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate recruiter
$decoded = authenticateJWT(['recruiter', 'admin']);
$role = strtolower($decoded['role'] ?? '');
$user_id = $decoded['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized", "status" => false]);
    exit;
}

try {
    if ($role !== 'recruiter') {
        http_response_code(403);
        echo json_encode(["message" => "Access denied", "status" => false]);
        exit;
    }

    // 🔹 Get recruiter_profile id (REMOVED admin_action condition)
    $sql_rec = "SELECT id FROM recruiter_profiles WHERE user_id = ?";
    $stmt_rec = $conn->prepare($sql_rec);
    $stmt_rec->bind_param("i", $user_id);
    $stmt_rec->execute();
    $rec = $stmt_rec->get_result()->fetch_assoc();
    $recruiter_profile_id = $rec['id'] ?? null;

    if (!$recruiter_profile_id) {
        http_response_code(400);
        echo json_encode(["message" => "Recruiter profile not found", "status" => false]);
        exit;
    }

    // 🔹 Get optional month filter (if provided)
    $month = isset($_GET['month']) ? intval($_GET['month']) : null;
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;

    // 🔹 Fetch ALL scheduled interviews (not just one)
    $sql = "
        SELECT 
            u.user_name AS candidate_name,
            j.title AS job_title,
            i.mode AS interview_mode,
            i.location AS interview_location,
            DATE_FORMAT(i.scheduled_at, '%h:%i %p') AS interview_time,
            DATE(i.scheduled_at) AS interview_date
        FROM interviews i
        INNER JOIN applications a ON a.id = i.application_id
        INNER JOIN jobs j ON j.id = a.job_id
        INNER JOIN student_profiles sp ON sp.id = a.student_id
        INNER JOIN users u ON u.id = sp.user_id
        WHERE j.recruiter_id = ?
          AND i.status = 'scheduled'
          AND (i.deleted_at IS NULL OR i.deleted_at = '0000-00-00 00:00:00')";

    // Add month filter if provided
    $params = [$recruiter_profile_id];
    $types = "i";
    
    if ($month !== null && $month >= 1 && $month <= 12) {
        if ($year !== null && $year > 0) {
            $sql .= " AND YEAR(i.scheduled_at) = ? AND MONTH(i.scheduled_at) = ?";
            $params[] = $year;
            $params[] = $month;
            $types .= "ii";
        } else {
            $sql .= " AND MONTH(i.scheduled_at) = ?";
            $params[] = $month;
            $types .= "i";
        }
    }

    $sql .= " ORDER BY i.scheduled_at ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // 🔹 Fetch ALL interviews into an array
    $interviews = [];
    while ($row = $result->fetch_assoc()) {
        $interviews[] = [
            "name" => $row['candidate_name'],
            "job_title" => $row['job_title'],
            "mode" => ucfirst($row['interview_mode']),
            "location" => $row['interview_location'] ?? "",
            "time" => $row['interview_time'],
            "scheduled_at" => $row['interview_date']
        ];
    }

    $stmt->close();

    // Return all interviews as an array
    http_response_code(200);
    echo json_encode([
        "candidate_interview_details" => $interviews,
        "status" => true,
        "total" => count($interviews)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => $e->getMessage(), "status" => false]);
}

$conn->close();
?>
