<?php
require_once '../../cors.php';
require_once '../../db.php';

// Candidate/Student authentication
$decoded = authenticateJWT(['student']);

try {
    // Get only LIVE drives
    $sql = "SELECT 
                cd.*,
                COUNT(DISTINCT cdc.id) as total_companies,
                COUNT(DISTINCT ca.id) as total_applications
            FROM campus_drives cd
            LEFT JOIN campus_drive_companies cdc ON cd.id = cdc.drive_id
            LEFT JOIN campus_applications ca ON cd.id = ca.drive_id
            WHERE cd.status = 'live'
            GROUP BY cd.id
            ORDER BY cd.start_date ASC, cd.created_at DESC";
    
    $result = $conn->query($sql);
    $drives = [];
    
    while ($row = $result->fetch_assoc()) {
        // Check if student has already applied
        $student_id = $decoded['user_id'];
        $applied_check = $conn->query("
            SELECT id, status, assigned_day_id 
            FROM campus_applications 
            WHERE drive_id = {$row['id']} AND student_id = $student_id
        ");
        
        $row['has_applied'] = $applied_check->num_rows > 0;
        if ($row['has_applied']) {
            $app_data = $applied_check->fetch_assoc();
            $row['application_status'] = $app_data['status'];
            
            // Get assigned day info
            if ($app_data['assigned_day_id']) {
                $day_info = $conn->query("
                    SELECT date, day_number 
                    FROM campus_drive_days 
                    WHERE id = {$app_data['assigned_day_id']}
                ");
                if ($day_info->num_rows > 0) {
                    $day_data = $day_info->fetch_assoc();
                    $row['assigned_day'] = "Day " . $day_data['day_number'];
                    $row['assigned_date'] = $day_data['date'];
                }
            }
        }
        
        $drives[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Live campus drives fetched successfully",
        "data" => $drives,
        "count" => count($drives)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching live campus drives",
        "error" => $e->getMessage()
    ]);
}
?>


