<?php
// get_recruiter_weekly_stats.php - Recruiter Dashboard API (Weekly Applicants + Trade/Job Insights)
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
    // ✅ STEP 2: Weekly Applicants by Job (cards ke liye) – SAME AS BEFORE
    // -------------------------------
    $sql_weekly = "
        SELECT 
            j.id AS job_id,
            j.title AS job_title,
            jc.category_name AS trade_name,
            COUNT(a.id) AS total_applications,
            SUM(
                CASE 
                    WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                    THEN 1 ELSE 0 
                END
            ) AS new_applications
        FROM jobs j
        LEFT JOIN job_category jc ON j.category_id = jc.id
        LEFT JOIN applications a ON a.job_id = j.id
        WHERE j.recruiter_id = ? 
          AND j.admin_action = 'approved'
        GROUP BY j.id, j.title, jc.category_name
        ORDER BY total_applications DESC;
    ";

    $stmt_weekly = $conn->prepare($sql_weekly);
    $stmt_weekly->bind_param("i", $recruiter_id);
    $stmt_weekly->execute();
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
    // ✅ STEP 3: CHART DATA – TOP JOBS THIS MONTH (NEW LOGIC)
    // -------------------------------
    // Current month range
    // Applications counted ONLY if created_at is within current month
    $sql_chart = "
        SELECT 
            j.id AS job_id,
            j.title AS job_title,
            COUNT(a.id) AS total_applications
        FROM jobs j
        LEFT JOIN applications a 
            ON a.job_id = j.id
           AND a.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
           AND a.created_at <  DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
        WHERE j.recruiter_id = ?
          AND j.admin_action = 'approved'
        GROUP BY j.id, j.title
        HAVING total_applications > 0
        ORDER BY total_applications DESC
        LIMIT 10
    ";

    $stmt_chart = $conn->prepare($sql_chart);
    $stmt_chart->bind_param("i", $recruiter_id);
    $stmt_chart->execute();
    $res_chart = $stmt_chart->get_result();

    $chart_data = [];
    while ($row = $res_chart->fetch_assoc()) {
        $chart_data[] = [
            // 👇 frontend key naam 'trade' hi rakha, value me job_title daal diya
            "trade" => $row['job_title'],
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
