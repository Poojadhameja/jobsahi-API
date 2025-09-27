<?php
// get_reports.php - Get platform analytics reports (Admin access only)
require_once '../cors.php';

// ✅ Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']); // returns array

try {
    // First, let's check what columns exist in reports table
    $checkReports = $conn->query("DESCRIBE reports");
    
    if (!$checkReports) {
        throw new Exception("Cannot access reports table structure");
    }
    
    // Get column names for reports table
    $reportsColumns = [];
    while ($row = $checkReports->fetch_assoc()) {
        $reportsColumns[] = $row['Field'];
    }
    
    // Determine the correct ID column name
    $idColumn = 'id'; // default
    if (in_array('report_id', $reportsColumns)) {
        $idColumn = 'report_id';
    } elseif (in_array('id', $reportsColumns)) {
        $idColumn = 'id';
    }
    
    // Check if required columns exist in reports table based on the actual schema
    $generatedByColumn = in_array('generated_by', $reportsColumns) ? 'generated_by' : 'NULL';
    $reportTypeColumn = in_array('report_type', $reportsColumns) ? 'report_type' : 'NULL';
    $filtersAppliedColumn = in_array('filters_applied', $reportsColumns) ? 'filters_applied' : 'NULL';
    $downloadUrlColumn = in_array('download_url', $reportsColumns) ? 'download_url' : 'NULL';
    $generatedAtColumn = in_array('generated_at', $reportsColumns) ? 'generated_at' : 'NULL';
    
    // Build the query with correct column names matching the actual schema
    $stmt = $conn->prepare("
        SELECT 
            {$idColumn} as id,
            {$generatedByColumn} as generated_by,
            {$reportTypeColumn} as report_type,
            {$filtersAppliedColumn} as filters_applied,
            {$downloadUrlColumn} as download_url,
            {$generatedAtColumn} as generated_at
        FROM reports
        ORDER BY {$generatedAtColumn} DESC
    ");
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $reports = [];
        
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        
        echo json_encode([
            "status" => true,
            "message" => "Platform analytics reports retrieved successfully",
            "data" => $reports,
            "count" => count($reports)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve platform analytics reports",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>