<?php
// reports.php - Reports list with role-based access (JWT required)
require_once(__DIR__ . '/../../cors.php');
// Authenticate JWT - returns decoded payload
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 

try {
    $role = $decoded['role']; // JWT role field

    // Role-based SQL filter for admin_action
    if ($role === 'admin') {
        // Admin sees both pending and approved
        $actionFilter = "admin_action IN ('pending', 'approved')";
    } else {
        // Other roles see only approved
        $actionFilter = "admin_action = 'approved'";
    }

    // Get reports list from reports table
    $stmt = $conn->prepare("
        SELECT 
            id,
            report_type,
            filters_applied,
            download_url,
            generated_at,
            admin_action,
            CASE 
                WHEN report_type = 'revenue_report' THEN 'Revenue Report'
                WHEN report_type = 'job_summary' THEN 'Job Summary Report'
                WHEN report_type = 'placement_funnel' THEN 'Placement Funnel Report'
                ELSE UPPER(SUBSTRING(report_type, 1, 1)) + LOWER(SUBSTRING(report_type, 2))
            END AS report_title
        FROM reports
        WHERE $actionFilter
        ORDER BY generated_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            'id' => (int)$row['id'],
            'report_type' => $row['report_type'],
            'report_title' => $row['report_title'],
            'filters_applied' => $row['filters_applied'],
            'download_url' => $row['download_url'],
            'generated_at' => $row['generated_at'],
            'admin_action' => $row['admin_action']
        ];
    }

    // Get summary statistics
    $stmt2 = $conn->prepare("
        SELECT 
            COUNT(*) AS total_reports,
            COUNT(CASE WHEN report_type='revenue_report' THEN 1 END) AS revenue_reports,
            COUNT(CASE WHEN report_type='job_summary' THEN 1 END) AS job_summary_reports,
            COUNT(CASE WHEN report_type='placement_funnel' THEN 1 END) AS placement_funnel_reports,
            COUNT(CASE WHEN DATE(generated_at) = CURDATE() THEN 1 END) AS generated_today,
            COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS generated_this_week,
            COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS generated_this_month,
            COUNT(CASE WHEN admin_action='pending' THEN 1 END) AS pending_reports,
            COUNT(CASE WHEN admin_action='approved' THEN 1 END) AS approved_reports
        FROM reports
        WHERE $actionFilter
    ");
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $summary = $result2->fetch_assoc();

    // Convert string numbers to integers
    foreach ($summary as $key => $value) {
        $summary[$key] = (int)$value;
    }

    echo json_encode([
        "status" => true,
        "message" => "Reports list retrieved successfully",
        "data" => [
            "reports" => $reports,
            "summary" => $summary,
            "total_count" => count($reports)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>