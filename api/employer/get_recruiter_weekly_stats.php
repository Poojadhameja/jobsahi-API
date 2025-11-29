<?php
// get_recruiter_weekly_stats.php - Recruiter Dashboard API (Weekly Applicants + Trade/Job Insights)
require_once '../cors.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
require_once '../db.php';

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
    // ✅ STEP 1: Get Recruiter Profile ID (REMOVED admin_action condition)
    // -------------------------------
    $sql_recruiter = "
        SELECT id 
        FROM recruiter_profiles 
        WHERE user_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql_recruiter);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recruiter = $result->fetch_assoc();
    $stmt->close();

    if (!$recruiter) {
        http_response_code(400);
        echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
        exit;
    }

    $recruiter_id = intval($recruiter['id']);

    // -------------------------------
    // ✅ STEP 2: Weekly Applicants by Job - TOTAL APPLICATIONS PER JOB
    // -------------------------------
    // Logic: Ab tak kitne logo ne apply kiya particular job par (total count)
    // Chahe wo select ho, reject ho, kuch bhi ho - TOTAL count dikhana hai
    $sql_weekly = "
        SELECT 
            j.id AS job_id,
            j.title AS job_title,
            COALESCE(jc.category_name, 'Uncategorized') AS trade_name,
            COUNT(a.id) AS total_applications,
            SUM(
                CASE 
                    WHEN a.applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                    THEN 1 ELSE 0 
                END
            ) AS new_applications
        FROM jobs j
        LEFT JOIN job_category jc ON j.category_id = jc.id
        LEFT JOIN applications a ON a.job_id = j.id
        WHERE j.recruiter_id = ?
        GROUP BY j.id, j.title, jc.category_name
        ORDER BY total_applications DESC, j.id DESC;
    ";

    $stmt_weekly = $conn->prepare($sql_weekly);
    if (!$stmt_weekly) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt_weekly->bind_param("i", $recruiter_id);
    if (!$stmt_weekly->execute()) {
        throw new Exception("Database execute error: " . $stmt_weekly->error);
    }
    $result_weekly = $stmt_weekly->get_result();

    $weekly_applicants = [];

    while ($row = $result_weekly->fetch_assoc()) {
        $trade = $row['trade_name'] ?? $row['job_title'];

        // ✅ Weekly cards data (unchanged)
        $weekly_applicants[] = [
            'job_id'             => intval($row['job_id']),
            'job_title'          => $row['job_title'],
            'trade'              => $trade,
            'total_applications' => intval($row['total_applications']),
            'new_applications'   => intval($row['new_applications'])
        ];
    }
    $stmt_weekly->close();

    // -------------------------------
    // ✅ STEP 3: CHART DATA – TOP JOBS BY APPLICATIONS (CURRENT MONTH)
    // -------------------------------
    // Logic: Current month me sabse highest applications wale jobs dikhana hai
    // Format: Job Title (category) - Only show data for logged-in recruiter
    $sql_chart = "
        SELECT 
            j.title AS job_title,
            COALESCE(jc.category_name, 'Uncategorized') AS category_name,
            COUNT(a.id) AS total_applications
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        LEFT JOIN job_category jc ON j.category_id = jc.id
        WHERE j.recruiter_id = ?
          AND a.applied_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND a.applied_at < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
        GROUP BY j.id, j.title, jc.category_name
        HAVING total_applications > 0
        ORDER BY total_applications DESC
        LIMIT 10
    ";

    $stmt_chart = $conn->prepare($sql_chart);
    if (!$stmt_chart) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt_chart->bind_param("i", $recruiter_id);
    if (!$stmt_chart->execute()) {
        throw new Exception("Database execute error: " . $stmt_chart->error);
    }
    $res_chart = $stmt_chart->get_result();

    $chart_data = [];
    while ($row = $res_chart->fetch_assoc()) {
        // Format: Job Title (category)
        $trade_display = $row['job_title'];
        if (!empty($row['category_name'])) {
            $trade_display .= ' (' . $row['category_name'] . ')';
        }
        $chart_data[] = [
            "trade" => $trade_display,
            "total_applications" => intval($row['total_applications'])
        ];
    }
    $stmt_chart->close();

    // -------------------------------
    // ✅ STEP 4: Final Response
    // -------------------------------
    echo json_encode([
        "status" => true,
        "message" => "Weekly applicants fetched successfully",
        "chart_data" => $chart_data,               // NOW: top jobs of this month by applications
        "weekly_applicants" => $weekly_applicants, // same as before
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
