<?php
require_once '../../cors.php';
require_once '../../db.php';

// Candidate/Student authentication
$decoded = authenticateJWT(['student']);

// Get application_id from query parameter
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;

if ($application_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Invalid or missing application_id"
    ]);
    exit;
}

$student_id = $decoded['user_id'];

try {
    // Get application details (only own applications)
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
                -- Preference companies with full details
                cdc1.company_id as pref1_company_id,
                rp1.company_name as pref1_company_name,
                rp1.company_logo as pref1_logo,
                cdc1.job_roles as pref1_job_roles,
                cdc1.criteria as pref1_criteria,
                cdc2.company_id as pref2_company_id,
                rp2.company_name as pref2_company_name,
                rp2.company_logo as pref2_logo,
                cdc2.job_roles as pref2_job_roles,
                cdc2.criteria as pref2_criteria,
                cdc3.company_id as pref3_company_id,
                rp3.company_name as pref3_company_name,
                rp3.company_logo as pref3_logo,
                cdc3.job_roles as pref3_job_roles,
                cdc3.criteria as pref3_criteria,
                cdc4.company_id as pref4_company_id,
                rp4.company_name as pref4_company_name,
                rp4.company_logo as pref4_logo,
                cdc4.job_roles as pref4_job_roles,
                cdc4.criteria as pref4_criteria,
                cdc5.company_id as pref5_company_id,
                rp5.company_name as pref5_company_name,
                rp5.company_logo as pref5_logo,
                cdc5.job_roles as pref5_job_roles,
                cdc5.criteria as pref5_criteria,
                cdc6.company_id as pref6_company_id,
                rp6.company_name as pref6_company_name,
                rp6.company_logo as pref6_logo,
                cdc6.job_roles as pref6_job_roles,
                cdc6.criteria as pref6_criteria
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
            WHERE ca.id = $application_id AND ca.student_id = $student_id";
    
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Application not found"
        ]);
        exit;
    }
    
    $application = $result->fetch_assoc();
    
    // Format preferences with full details
    $application['preferences'] = [
        [
            'preference_number' => 1,
            'company_id' => $application['pref1_company_id'],
            'company_name' => $application['pref1_company_name'],
            'logo' => $application['pref1_logo'],
            'job_roles' => json_decode($application['pref1_job_roles'], true),
            'criteria' => json_decode($application['pref1_criteria'], true)
        ],
        [
            'preference_number' => 2,
            'company_id' => $application['pref2_company_id'],
            'company_name' => $application['pref2_company_name'],
            'logo' => $application['pref2_logo'],
            'job_roles' => json_decode($application['pref2_job_roles'], true),
            'criteria' => json_decode($application['pref2_criteria'], true)
        ],
        [
            'preference_number' => 3,
            'company_id' => $application['pref3_company_id'],
            'company_name' => $application['pref3_company_name'],
            'logo' => $application['pref3_logo'],
            'job_roles' => json_decode($application['pref3_job_roles'], true),
            'criteria' => json_decode($application['pref3_criteria'], true)
        ],
        [
            'preference_number' => 4,
            'company_id' => $application['pref4_company_id'],
            'company_name' => $application['pref4_company_name'],
            'logo' => $application['pref4_logo'],
            'job_roles' => json_decode($application['pref4_job_roles'], true),
            'criteria' => json_decode($application['pref4_criteria'], true)
        ],
        [
            'preference_number' => 5,
            'company_id' => $application['pref5_company_id'],
            'company_name' => $application['pref5_company_name'],
            'logo' => $application['pref5_logo'],
            'job_roles' => json_decode($application['pref5_job_roles'], true),
            'criteria' => json_decode($application['pref5_criteria'], true)
        ],
        [
            'preference_number' => 6,
            'company_id' => $application['pref6_company_id'],
            'company_name' => $application['pref6_company_name'],
            'logo' => $application['pref6_logo'],
            'job_roles' => json_decode($application['pref6_job_roles'], true),
            'criteria' => json_decode($application['pref6_criteria'], true)
        ]
    ];
    
    $application['assigned_day'] = $application['day_number'] ? "Day " . $application['day_number'] : null;
    
    // Remove individual pref fields
    for ($i = 1; $i <= 6; $i++) {
        unset($application["pref{$i}_company_id"]);
        unset($application["pref{$i}_company_name"]);
        unset($application["pref{$i}_logo"]);
        unset($application["pref{$i}_job_roles"]);
        unset($application["pref{$i}_criteria"]);
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Application details fetched successfully",
        "data" => $application
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error fetching application details",
        "error" => $e->getMessage()
    ]);
}
?>

