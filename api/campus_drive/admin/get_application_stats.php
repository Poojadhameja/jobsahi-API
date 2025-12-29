<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

$drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : null;

try {
    $where_clause = $drive_id ? "WHERE drive_id = $drive_id" : "";
    
    // Overall stats
    $stats_sql = "SELECT 
                    COUNT(*) as total_applications,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) as selected
                  FROM campus_applications
                  $where_clause";
    
    $stats_result = $conn->query($stats_sql);
    $stats = $stats_result->fetch_assoc();
    
    // Stats by day
    $days_sql = "SELECT 
                    cdd.id,
                    cdd.date,
                    cdd.day_number,
                    cdd.capacity,
                    cdd.filled_count,
                    COUNT(ca.id) as total_applications,
                    SUM(CASE WHEN ca.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN ca.status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
                    SUM(CASE WHEN ca.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN ca.status = 'selected' THEN 1 ELSE 0 END) as selected
                 FROM campus_drive_days cdd
                 LEFT JOIN campus_applications ca ON cdd.id = ca.assigned_day_id";
    
    if ($drive_id) {
        $days_sql .= " WHERE cdd.drive_id = $drive_id";
    }
    
    $days_sql .= " GROUP BY cdd.id ORDER BY cdd.date ASC";
    
    $days_result = $conn->query($days_sql);
    $days_stats = [];
    while ($row = $days_result->fetch_assoc()) {
        $days_stats[] = $row;
    }
    
    // Stats by company (top preferences)
    $company_sql = "SELECT 
                        rp.id as company_id,
                        rp.company_name,
                        COUNT(DISTINCT CASE WHEN ca.pref1_company_id = cdc.id THEN ca.id END) as pref1_count,
                        COUNT(DISTINCT CASE WHEN ca.pref2_company_id = cdc.id THEN ca.id END) as pref2_count,
                        COUNT(DISTINCT CASE WHEN ca.pref3_company_id = cdc.id THEN ca.id END) as pref3_count,
                        COUNT(DISTINCT CASE WHEN ca.pref4_company_id = cdc.id THEN ca.id END) as pref4_count,
                        COUNT(DISTINCT CASE WHEN ca.pref5_company_id = cdc.id THEN ca.id END) as pref5_count,
                        COUNT(DISTINCT CASE WHEN ca.pref6_company_id = cdc.id THEN ca.id END) as pref6_count,
                        COUNT(DISTINCT ca.id) as total_preferences
                    FROM campus_drive_companies cdc
                    LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
                    LEFT JOIN campus_applications ca ON (
                        ca.pref1_company_id = cdc.id OR
                        ca.pref2_company_id = cdc.id OR
                        ca.pref3_company_id = cdc.id OR
                        ca.pref4_company_id = cdc.id OR
                        ca.pref5_company_id = cdc.id OR
                        ca.pref6_company_id = cdc.id
                    )";
    
    if ($drive_id) {
        $company_sql .= " WHERE cdc.drive_id = $drive_id";
        $company_sql .= " AND ca.drive_id = $drive_id";
    }
    
    $company_sql .= " GROUP BY rp.id, rp.company_name ORDER BY total_preferences DESC";
    
    $company_result = $conn->query($company_sql);
    $company_stats = [];
    while ($row = $company_result->fetch_assoc()) {
        $company_stats[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Application statistics fetched successfully",
        "data" => [
            "overall" => $stats,
            "by_day" => $days_stats,
            "by_company" => $company_stats
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching application statistics",
        "error" => $e->getMessage()
    ]);
}
?>


