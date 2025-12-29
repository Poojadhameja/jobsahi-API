<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

// Get drive_id from query parameter
$drive_id = isset($_GET['drive_id']) ? intval($_GET['drive_id']) : 0;

if ($drive_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Invalid or missing drive_id"
    ]);
    exit;
}

try {
    // Get drive details
    $drive_result = $conn->query("SELECT * FROM campus_drives WHERE id = $drive_id");
    if ($drive_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Campus drive not found"
        ]);
        exit;
    }
    
    $drive = $drive_result->fetch_assoc();
    
    // Get companies
    $companies_result = $conn->query("
        SELECT 
            cdc.*,
            rp.company_name,
            rp.company_logo as logo
        FROM campus_drive_companies cdc
        LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
        WHERE cdc.drive_id = $drive_id
        ORDER BY cdc.id ASC
    ");
    
    $companies = [];
    while ($row = $companies_result->fetch_assoc()) {
        $companies[] = $row;
    }
    
    // Get days
    $days_result = $conn->query("
        SELECT * FROM campus_drive_days 
        WHERE drive_id = $drive_id 
        ORDER BY date ASC
    ");
    
    $days = [];
    while ($row = $days_result->fetch_assoc()) {
        $days[] = $row;
    }
    
    // Get application stats
    $stats_result = $conn->query("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) as selected
        FROM campus_applications
        WHERE drive_id = $drive_id
    ");
    
    $stats = $stats_result->fetch_assoc();
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Campus drive details fetched successfully",
        "data" => [
            "drive" => $drive,
            "companies" => $companies,
            "days" => $days,
            "stats" => $stats
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching campus drive details",
        "error" => $e->getMessage()
    ]);
}
?>

