<?php
// placements.php - Placement analytics report with role-based access (JWT required)
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
    
    // Placement analytics summary from reports table
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_reports,
            COUNT(CASE WHEN report_type='placement_funnel' THEN 1 END) AS placement_funnel_reports,
            COUNT(CASE WHEN report_type='job_summary' THEN 1 END) AS job_summary_reports,
            COUNT(CASE WHEN report_type='revenue_report' THEN 1 END) AS revenue_reports,
            COUNT(CASE WHEN DATE(generated_at) = CURDATE() THEN 1 END) AS generated_today,
            COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS generated_this_week,
            COUNT(CASE WHEN DATE(generated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS generated_this_month,
            COUNT(CASE WHEN admin_action='approved' THEN 1 END) AS approved_reports,
            COUNT(CASE WHEN admin_action='pending' THEN 1 END) AS pending_reports
        FROM reports
        WHERE $actionFilter
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $analytics = $result->fetch_assoc();
    
    // Report distribution by type
    $stmt2 = $conn->prepare("
        SELECT 
            report_type AS category,
            COUNT(*) AS report_count,
            MAX(generated_at) AS latest_generated
        FROM reports
        WHERE report_type IS NOT NULL AND $actionFilter
        GROUP BY report_type
        ORDER BY report_count DESC
    ");
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $category_distribution = [];
    while ($row = $result2->fetch_assoc()) {
        $category_distribution[] = $row;
    }
    
    // Recent placement reports with download links
    $stmt3 = $conn->prepare("
        SELECT 
            id,
            report_type,
            filters_applied,
            download_url,
            generated_at,
            admin_action
        FROM reports
        WHERE $actionFilter
        ORDER BY generated_at DESC
        LIMIT 10
    ");
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    $recent_reports = [];
    while ($row = $result3->fetch_assoc()) {
        $recent_reports[] = $row;
    }
    
    echo json_encode([
        "status" => true,
        "message" => "Placement analytics retrieved successfully",
        "data" => [
            "summary" => $analytics,
            "category_distribution" => $category_distribution,
            "recent_reports" => $recent_reports
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