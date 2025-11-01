<?php
// get_recruiter_weekly_stats.php - Recruiter Dashboard API (Only Weekly Applicants Section)
require_once '../cors.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
include "../db.php";

// ✅ Authenticate user (only recruiter)
$decoded = authenticateJWT(['recruiter']);
$role = strtolower($decoded['role'] ?? '');
$user_id = $decoded['user_id'] ?? null;

if (!$user_id || $role !== 'recruiter') {
    http_response_code(403);
    echo json_encode(["message" => "Access denied", "status" => false]);
    exit;
}

try {
    $weekly_applicants = [];

    // -------------------------------
    // ✅ STEP 1: Get Recruiter Profile ID
    // -------------------------------
    $sql_recruiter = "SELECT id FROM recruiter_profiles 
                      WHERE user_id = ? AND admin_action = 'approved'";
    $stmt_recruiter = $conn->prepare($sql_recruiter);
    $stmt_recruiter->bind_param("i", $user_id);
    $stmt_recruiter->execute();
    $result_recruiter = $stmt_recruiter->get_result();
    $recruiter = $result_recruiter->fetch_assoc();
    $recruiter_id = $recruiter['id'] ?? null;

    if (!$recruiter_id) {
        http_response_code(404);
        echo json_encode(["message" => "Recruiter profile not found", "status" => false]);
        exit;
    }

    // -------------------------------
    // ✅ STEP 2: Weekly Applicants by Trade
    // -------------------------------
    $sql_weekly = "
        SELECT 
            j.title AS trade,
            COUNT(a.id) AS total_applications,
            SUM(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_applications
        FROM applications a
        INNER JOIN jobs j ON j.id = a.job_id
        WHERE j.recruiter_id = ? AND j.admin_action = 'approved'
        GROUP BY j.title
        ORDER BY total_applications DESC;
    ";

    $stmt_weekly = $conn->prepare($sql_weekly);
    $stmt_weekly->bind_param("i", $recruiter_id);
    $stmt_weekly->execute();
    $result_weekly = $stmt_weekly->get_result();

    while ($row = $result_weekly->fetch_assoc()) {
        $weekly_applicants[] = [
            'trade' => $row['trade'],
            'total_applications' => (int)$row['total_applications'],
            'new_applications' => (int)$row['new_applications']
        ];
    }

    // -------------------------------
    // ✅ STEP 3: Final JSON Response
    // -------------------------------
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Weekly applicants fetched successfully",
        "weekly_applicants" => $weekly_applicants
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
