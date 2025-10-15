<?php
require_once '../cors.php';

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

    // 🔹 Get recruiter_profile id
    $sql_rec = "SELECT id FROM recruiter_profiles WHERE user_id = ? AND admin_action = 'approved'";
    $stmt_rec = $conn->prepare($sql_rec);
    $stmt_rec->bind_param("i", $user_id);
    $stmt_rec->execute();
    $rec = $stmt_rec->get_result()->fetch_assoc();
    $recruiter_profile_id = $rec['id'] ?? null;

    if (!$recruiter_profile_id) {
        http_response_code(400);
        echo json_encode(["message" => "Recruiter profile not found or not approved", "status" => false]);
        exit;
    }

    // 🔹 Fetch latest 5 applicants
    $sql = "
        SELECT 
            u.user_name AS candidate_name,
            j.title AS job_title,
            DATE_FORMAT(a.applied_at, '%d-%m-%y') AS applied_date,
            a.status
        FROM applications a
        INNER JOIN jobs j ON j.id = a.job_id
        INNER JOIN student_profiles sp ON sp.id = a.student_id
        INNER JOIN users u ON u.id = sp.user_id
        WHERE j.recruiter_id = ?
        ORDER BY a.applied_at DESC
        LIMIT 5";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recruiter_profile_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $recent_applicants = [];
    while ($r = $result->fetch_assoc()) {
        $recent_applicants[] = $r;
    }

    http_response_code(200);
    echo json_encode([
        "recent_applicants" => $recent_applicants,
        "status" => true
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => $e->getMessage(), "status" => false]);
}

$conn->close();
?>
