<?php
require_once '../../cors.php';
require_once '../../db.php';

// Candidate/Student authentication
$decoded = authenticateJWT(['student']);

$student_id = $decoded['user_id'];

try {
    $sql = "SELECT 
                ca.*,
                cd.title as drive_title,
                cd.organizer,
                cd.venue,
                cd.city,
                cd.start_date as drive_start_date,
                cd.end_date as drive_end_date,
                cdd.date as assigned_date,
                cdd.day_number,
                -- Preference companies
                rp1.company_name as pref1_company_name,
                rp2.company_name as pref2_company_name,
                rp3.company_name as pref3_company_name,
                rp4.company_name as pref4_company_name,
                rp5.company_name as pref5_company_name,
                rp6.company_name as pref6_company_name
            FROM campus_applications ca
            LEFT JOIN campus_drives cd ON ca.drive_id = cd.id
            LEFT JOIN campus_drive_days cdd ON ca.assigned_day_id = cdd.id
            LEFT JOIN campus_drive_companies cdc1 ON ca.pref1_company_id = cdc1.id
            LEFT JOIN recruiter_profiles rp1 ON cdc1.company_id = rp1.id
            LEFT JOIN campus_drive_companies cdc2 ON ca.pref2_company_id = cdc2.id
            LEFT JOIN recruiter_profiles rp2 ON cdc2.company_id = rp2.id
            LEFT JOIN campus_drive_companies cdc3 ON ca.pref3_company_id = cdc3.id
            LEFT JOIN recruiter_profiles rp3 ON cdc3.company_id = rp3.id
            LEFT JOIN campus_drive_companies cdc4 ON ca.pref4_company_id = cdc4.id
            LEFT JOIN recruiter_profiles rp4 ON cdc4.company_id = rp4.id
            LEFT JOIN campus_drive_companies cdc5 ON ca.pref5_company_id = cdc5.id
            LEFT JOIN recruiter_profiles rp5 ON cdc5.company_id = rp5.id
            LEFT JOIN campus_drive_companies cdc6 ON ca.pref6_company_id = cdc6.id
            LEFT JOIN recruiter_profiles rp6 ON cdc6.company_id = rp6.id
            WHERE ca.student_id = $student_id
            ORDER BY ca.applied_at DESC";
    
    $result = $conn->query($sql);
    $applications = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format preferences
        $row['preferences'] = [
            [
                'company_name' => $row['pref1_company_name']
            ],
            [
                'company_name' => $row['pref2_company_name']
            ],
            [
                'company_name' => $row['pref3_company_name']
            ],
            [
                'company_name' => $row['pref4_company_name']
            ],
            [
                'company_name' => $row['pref5_company_name']
            ],
            [
                'company_name' => $row['pref6_company_name']
            ]
        ];
        
        $row['assigned_day'] = $row['day_number'] ? "Day " . $row['day_number'] : null;
        
        // Remove individual pref fields
        unset($row['pref1_company_name'], $row['pref2_company_name'], $row['pref3_company_name']);
        unset($row['pref4_company_name'], $row['pref5_company_name'], $row['pref6_company_name']);
        unset($row['pref1_company_id'], $row['pref2_company_id'], $row['pref3_company_id']);
        unset($row['pref4_company_id'], $row['pref5_company_id'], $row['pref6_company_id']);
        
        $applications[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Applications fetched successfully",
        "data" => $applications,
        "count" => count($applications)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching applications",
        "error" => $e->getMessage()
    ]);
}
?>


