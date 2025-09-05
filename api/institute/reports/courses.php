<?php
// reports.php - Reports analytics (JWT protected)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include __DIR__ . "/../../db.php";
require_once __DIR__ . "/../../jwt_token/jwt_helper.php";
require_once __DIR__ . "/../../auth/auth_middleware.php";

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute']); 
$role = $decoded['role']; // Assuming 'role' exists in your JWT payload

try {
    // ✅ Apply role-based filter for admin_action
    if ($role === 'admin') {
        // Admin sees both pending and approval
        $sql = "
            SELECT 
                report_type,
                COUNT(*) AS total_reports,
                COUNT(CASE WHEN admin_action = 'pending' THEN 1 END) AS pending_reports,
                COUNT(CASE WHEN admin_action = 'approval' THEN 1 END) AS approved_reports,
                COUNT(CASE WHEN DATE(generated_at) = CURDATE() THEN 1 END) AS reports_today,
                COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS reports_this_week,
                COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS reports_this_month
            FROM reports
            WHERE (admin_action = 'pending' OR admin_action = 'approval')
            GROUP BY report_type
            ORDER BY total_reports DESC
        ";
    } else {
        // Recruiter, Institute → only approval
        $sql = "
            SELECT 
                report_type,
                COUNT(*) AS total_reports,
                COUNT(CASE WHEN admin_action = 'pending' THEN 1 END) AS pending_reports,
                COUNT(CASE WHEN admin_action = 'approval' THEN 1 END) AS approved_reports,
                COUNT(CASE WHEN DATE(generated_at) = CURDATE() THEN 1 END) AS reports_today,
                COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS reports_this_week,
                COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS reports_this_month
            FROM reports
            WHERE admin_action = 'approval'
            GROUP BY report_type
            ORDER BY total_reports DESC
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $reports_analytics = [];
    while ($row = $result->fetch_assoc()) {
        $reports_analytics[] = [
            'report_type' => $row['report_type'],
            'counts' => [
                'total' => (int)$row['total_reports'],
                'pending' => (int)$row['pending_reports'],
                'approved' => (int)$row['approved_reports']
            ],
            'timeline' => [
                'today' => (int)$row['reports_today'],
                'this_week' => (int)$row['reports_this_week'],
                'this_month' => (int)$row['reports_this_month']
            ]
        ];
    }

    // ✅ Overall Summary
    $total_report_types = count($reports_analytics);
    $total_reports = array_sum(array_column(array_column($reports_analytics, 'counts'), 'total'));
    $total_pending = array_sum(array_column(array_column($reports_analytics, 'counts'), 'pending'));
    $total_approved = array_sum(array_column(array_column($reports_analytics, 'counts'), 'approved'));

    // ✅ Most popular report types
    $popular_reports = array_slice($reports_analytics, 0, 5);

    echo json_encode([
        "status" => true,
        "message" => "Reports analytics retrieved successfully",
        "data" => [
            "summary" => [
                "total_report_types" => $total_report_types,
                "total_reports" => $total_reports,
                "total_pending" => $total_pending,
                "total_approved" => $total_approved,
                "approval_rate" => $total_reports > 0 ? round(($total_approved / $total_reports) * 100, 2) : 0
            ],
            "report_types" => $reports_analytics,
            "popular_reports" => $popular_reports
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error retrieving reports analytics: " . $e->getMessage()
    ]);
}

$conn->close();
?>