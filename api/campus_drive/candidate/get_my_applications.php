<?php
require_once '../../cors.php';
require_once '../../db.php';

// Candidate/Student authentication
$decoded = authenticateJWT(['student']);

$user_id = $decoded['user_id'];

try {
    // Get student_id from student_profiles (campus_applications uses student_profiles.id, not users.id)
    $stmt_student = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    if (!$stmt_student) {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Database error while fetching student profile",
            "error" => mysqli_error($conn)
        ]);
        exit;
    }
    
    $stmt_student->bind_param("i", $user_id);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();
    
    if ($result_student->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Student profile not found. Please complete your profile first."
        ]);
        $stmt_student->close();
        exit;
    }
    
    $student_profile = $result_student->fetch_assoc();
    $student_id = intval($student_profile['id']);
    $stmt_student->close();
    
    if ($student_id <= 0) {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Invalid student profile ID"
        ]);
        exit;
    }

    // Get all applications with full details
    $sql = "SELECT 
                ca.*,
                cd.id as drive_id,
                cd.title as drive_title,
                cd.organizer,
                cd.venue,
                cd.city,
                cd.start_date as drive_start_date,
                cd.end_date as drive_end_date,
                cd.status as drive_status,
                cdd.date as assigned_date,
                cdd.day_number,
                -- Preference companies with full details
                cdc1.id as pref1_drive_company_id,
                cdc1.company_id as pref1_company_id,
                rp1.company_name as pref1_company_name,
                rp1.company_logo as pref1_logo,
                cdc1.job_roles as pref1_job_roles,
                cdc1.criteria as pref1_criteria,
                cdc2.id as pref2_drive_company_id,
                cdc2.company_id as pref2_company_id,
                rp2.company_name as pref2_company_name,
                rp2.company_logo as pref2_logo,
                cdc2.job_roles as pref2_job_roles,
                cdc2.criteria as pref2_criteria,
                cdc3.id as pref3_drive_company_id,
                cdc3.company_id as pref3_company_id,
                rp3.company_name as pref3_company_name,
                rp3.company_logo as pref3_logo,
                cdc3.job_roles as pref3_job_roles,
                cdc3.criteria as pref3_criteria,
                cdc4.id as pref4_drive_company_id,
                cdc4.company_id as pref4_company_id,
                rp4.company_name as pref4_company_name,
                rp4.company_logo as pref4_logo,
                cdc4.job_roles as pref4_job_roles,
                cdc4.criteria as pref4_criteria,
                cdc5.id as pref5_drive_company_id,
                cdc5.company_id as pref5_company_id,
                rp5.company_name as pref5_company_name,
                rp5.company_logo as pref5_logo,
                cdc5.job_roles as pref5_job_roles,
                cdc5.criteria as pref5_criteria,
                cdc6.id as pref6_drive_company_id,
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
            WHERE ca.student_id = $student_id
            ORDER BY ca.applied_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . mysqli_error($conn));
    }
    
    $applications = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format preferences with full details (only include non-null preferences)
        $preferences = [];
        
        for ($i = 1; $i <= 6; $i++) {
            if (!empty($row["pref{$i}_company_id"])) {
                $preferences[] = [
                    'preference_number' => $i,
                    'drive_company_id' => $row["pref{$i}_drive_company_id"],
                    'company_id' => $row["pref{$i}_company_id"],
                    'company_name' => $row["pref{$i}_company_name"],
                    'logo' => $row["pref{$i}_logo"],
                    'job_roles' => json_decode($row["pref{$i}_job_roles"], true) ?: [],
                    'criteria' => json_decode($row["pref{$i}_criteria"], true) ?: []
                ];
            }
        }
        
        $row['preferences'] = $preferences;
        $row['assigned_day'] = $row['day_number'] ? "Day " . $row['day_number'] : null;
        
        // Remove individual pref fields
        for ($i = 1; $i <= 6; $i++) {
            unset($row["pref{$i}_drive_company_id"]);
            unset($row["pref{$i}_company_id"]);
            unset($row["pref{$i}_company_name"]);
            unset($row["pref{$i}_logo"]);
            unset($row["pref{$i}_job_roles"]);
            unset($row["pref{$i}_criteria"]);
        }
        
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


