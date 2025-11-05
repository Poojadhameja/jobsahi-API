<?php
// get_recruiter_weekly_stats.php - Recruiter Dashboard API (Weekly Applicants + Trade Insights)
require_once '../cors.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
include "../db.php";

// ✅ Authenticate recruiter
$decoded = authenticateJWT(['recruiter']);
$role = strtolower($decoded['role'] ?? '');
$user_id = intval($decoded['user_id'] ?? 0);

if ($role !== 'recruiter' || !$user_id) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Access denied"]);
    exit;
}

try {
    // -------------------------------
    // ✅ STEP 1: Get Recruiter Profile ID
    // -------------------------------
    $sql_recruiter = "
        SELECT id 
        FROM recruiter_profiles 
        WHERE user_id = ? AND admin_action = 'approved'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_recruiter);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recruiter = $result->fetch_assoc();

    if (!$recruiter) {
        echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
        exit;
    }

    $recruiter_id = intval($recruiter['id']);

    // -------------------------------
    // ✅ STEP 2: Weekly Applicants by Job (and Trade/Category)
    // -------------------------------
    $sql_weekly = "
        SELECT 
            j.id AS job_id,
            j.title AS job_title,
            jc.category_name AS trade_name,
            COUNT(a.id) AS total_applications,
            SUM(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS new_applications
        FROM jobs j
        LEFT JOIN job_category jc ON j.category_id = jc.id
        LEFT JOIN applications a ON a.job_id = j.id
        WHERE j.recruiter_id = ? AND j.admin_action = 'approved'
        GROUP BY j.id, j.title, jc.category_name
        ORDER BY total_applications DESC;
    ";

    $stmt_weekly = $conn->prepare($sql_weekly);
    $stmt_weekly->bind_param("i", $recruiter_id);
    $stmt_weekly->execute();
    $result_weekly = $stmt_weekly->get_result();

    $weekly_applicants = [];
    $trade_summary = []; // for chart (aggregate by trade)

    while ($row = $result_weekly->fetch_assoc()) {
        $trade = $row['trade_name'] ?? $row['job_title'];

        // ✅ Weekly cards data
        $weekly_applicants[] = [
            'job_id'             => intval($row['job_id']),
            'job_title'          => $row['job_title'],
            'trade'              => $trade,
            'total_applications' => intval($row['total_applications']),
            'new_applications'   => intval($row['new_applications'])
        ];

        // ✅ Chart data aggregation (trade-wise)
        if (!isset($trade_summary[$trade])) {
            $trade_summary[$trade] = 0;
        }
        $trade_summary[$trade] += intval($row['total_applications']);
    }

    // -------------------------------
    // ✅ STEP 3: Prepare Final Response
    // -------------------------------
    $chart_data = [];
    foreach ($trade_summary as $trade => $count) {
        $chart_data[] = [
            "trade" => $trade,
            "total_applications" => $count
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Weekly applicants fetched successfully",
        "chart_data" => $chart_data,             // for pie chart (trades-wise)
        "weekly_applicants" => $weekly_applicants, // for job cards section
        "date_range" => [
            "start" => date('M d', strtotime('-7 days')),
            "end"   => date('M d')
        ]
    ]);

} catch (Throwable $e) {
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
