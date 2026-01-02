<?php
require_once '../../cors.php';
require_once '../../db.php';

// Candidate/Student authentication
$decoded = authenticateJWT(['student']);

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
    // Get drive details (only LIVE drives visible to candidates)
    $drive_result = $conn->query("SELECT * FROM campus_drives WHERE id = $drive_id AND status = 'live'");
    if ($drive_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Campus drive not found or not available"
        ]);
        exit;
    }
    
    $drive = $drive_result->fetch_assoc();
    
    // Get companies
    // Note: cdc.id is the ID to use in preferences (campus_drive_companies.id)
    $companies_result = $conn->query("
        SELECT 
            cdc.id as preference_id,
            cdc.*,
            rp.company_name,
            rp.company_logo as logo,
            rp.industry as company_description,
            rp.location as company_location
        FROM campus_drive_companies cdc
        LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
        WHERE cdc.drive_id = $drive_id
        ORDER BY cdc.id ASC
    ");
    
    $companies = [];
    while ($row = $companies_result->fetch_assoc()) {
        // Parse JSON fields
        $row['job_roles'] = json_decode($row['job_roles'], true);
        $criteria = json_decode($row['criteria'], true);
        $row['criteria'] = $criteria;

        // Handle manual company details
        if (isset($criteria['manual_company_name'])) {
            $row['company_name'] = $criteria['manual_company_name'];
        }
        if (isset($criteria['manual_company_location'])) {
            $row['company_location'] = $criteria['manual_company_location'];
        }
        
        $companies[] = $row;
    }
    
    // Check if student has already applied
    $student_id = $decoded['user_id'];
    $applied_check = $conn->query("
        SELECT 
            ca.*,
            cdd.date as assigned_date,
            cdd.day_number
        FROM campus_applications ca
        LEFT JOIN campus_drive_days cdd ON ca.assigned_day_id = cdd.id
        WHERE ca.drive_id = $drive_id AND ca.student_id = $student_id
    ");
    
    $application = null;
    if ($applied_check->num_rows > 0) {
        $app_data = $applied_check->fetch_assoc();
        
        // Get preference company names
        $pref_companies = [];
        for ($i = 1; $i <= 6; $i++) {
            $pref_id = $app_data["pref{$i}_company_id"];
            if ($pref_id) {
                $pref_result = $conn->query("
                    SELECT 
                        cdc.id,
                        cdc.company_id,
                        rp.company_name
                    FROM campus_drive_companies cdc
                    LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
                    WHERE cdc.id = $pref_id
                ");
                if ($pref_result->num_rows > 0) {
                    $pref_companies[] = $pref_result->fetch_assoc();
                }
            }
        }
        
        $application = [
            'id' => $app_data['id'],
            'status' => $app_data['status'],
            'assigned_day' => $app_data['day_number'] ? "Day " . $app_data['day_number'] : null,
            'assigned_date' => $app_data['assigned_date'],
            'interview_date' => $app_data['interview_date'],
            'interview_time' => $app_data['interview_time'],
            'interview_venue' => $app_data['interview_venue'],
            'preferences' => $pref_companies,
            'applied_at' => $app_data['applied_at']
        ];
    }
    
    http_response_code(200);
    echo json_encode([
        "status" => true,
        "message" => "Campus drive details fetched successfully",
        "data" => [
            "drive" => $drive,
            "companies" => $companies,
            "application" => $application
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

