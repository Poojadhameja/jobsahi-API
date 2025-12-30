<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

try {
    $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : null;
    
    // Build query
    $sql = "SELECT 
                cd.*,
                COUNT(DISTINCT cdc.id) as total_companies,
                COUNT(DISTINCT ca.id) as total_applications,
                COUNT(DISTINCT cdd.id) as total_days
            FROM campus_drives cd
            LEFT JOIN campus_drive_companies cdc ON cd.id = cdc.drive_id
            LEFT JOIN campus_applications ca ON cd.id = ca.drive_id
            LEFT JOIN campus_drive_days cdd ON cd.id = cdd.drive_id";
    
    $conditions = [];
    if ($status && in_array($status, ['draft', 'live', 'closed'])) {
        $conditions[] = "cd.status = '$status'";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY cd.id ORDER BY cd.created_at DESC";
    
    $result = $conn->query($sql);
    $drives = [];
    
    while ($row = $result->fetch_assoc()) {
        $drives[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Campus drives fetched successfully",
        "data" => $drives,
        "count" => count($drives)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching campus drives",
        "error" => $e->getMessage()
    ]);
}
?>


