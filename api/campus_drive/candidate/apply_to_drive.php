<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, return as JSON
ini_set('log_errors', 1);

require_once '../../cors.php';
require_once '../../db.php';

try {
    // Candidate/Student authentication
    $decoded = authenticateJWT(['student']);
    
    if (!$decoded || !isset($decoded['user_id'])) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Authentication failed: Invalid token or user ID missing"
        ]);
        exit;
    }

    // Get request data
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid JSON in request body",
            "error" => json_last_error_msg()
        ]);
        exit;
    }

    // Validate required fields
    if (empty($input['drive_id'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Missing required field: drive_id"
        ]);
        exit;
    }

    // Validate preferences - exactly 6 required
    if (empty($input['preferences']) || !is_array($input['preferences']) || count($input['preferences']) !== 6) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Exactly 6 company preferences are required",
            "received_count" => is_array($input['preferences']) ? count($input['preferences']) : 0
        ]);
        exit;
    }

    $drive_id = intval($input['drive_id']);
    $user_id = intval($decoded['user_id']);
    $preferences = $input['preferences'];
    
    if ($drive_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid drive_id"
        ]);
        exit;
    }
    
    if ($user_id <= 0) {
        http_response_code(401);
        echo json_encode([
            "status" => false,
            "message" => "Invalid user_id"
        ]);
        exit;
    }
    
    // Get student_profile.id from user_id (foreign key constraint requires student_profiles.id, not users.id)
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
    $student_id = intval($student_profile['id']); // This is the actual student_id for foreign key
    $stmt_student->close();
    
    if ($student_id <= 0) {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Invalid student profile ID"
        ]);
        exit;
    }

    // Check if drive exists and is LIVE - include start_date and end_date
    $drive_check = $conn->query("SELECT id, status, capacity_per_day, start_date, end_date FROM campus_drives WHERE id = $drive_id");
    if (!$drive_check) {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Database error while checking drive",
            "error" => mysqli_error($conn)
        ]);
        exit;
    }
    
    if ($drive_check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Campus drive not found"
        ]);
        exit;
    }
    
    $drive = $drive_check->fetch_assoc();
    if (!$drive || empty($drive['start_date']) || empty($drive['end_date'])) {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Drive data incomplete: missing dates"
        ]);
        exit;
    }
    
    if ($drive['status'] !== 'live') {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Campus drive is not accepting applications",
            "current_status" => $drive['status']
        ]);
        exit;
    }

    // Check if student has already applied
    $existing = $conn->query("SELECT id FROM campus_applications WHERE drive_id = $drive_id AND student_id = $student_id");
    if (!$existing) {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Database error while checking existing application",
            "error" => mysqli_error($conn)
        ]);
        exit;
    }
    
    if ($existing->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "You have already applied to this campus drive"
        ]);
        exit;
    }

    // Validate all preferences are valid company IDs for this drive
    // Note: company_id in request refers to campus_drive_companies.id (not recruiter_profiles.id)
    $pref_ids = [];
    foreach ($preferences as $index => $pref) {
        if (empty($pref['company_id'])) {
            http_response_code(400);
            echo json_encode([
                "status" => false,
                "message" => "Invalid preference at position " . ($index + 1) . ": company_id is required"
            ]);
            exit;
        }
        
        $company_drive_id = intval($pref['company_id']); // This is campus_drive_companies.id
        
        // Check if this company is part of this drive
        $company_check = $conn->query("
            SELECT id FROM campus_drive_companies 
            WHERE id = $company_drive_id AND drive_id = $drive_id
        ");
        
        if (!$company_check) {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "message" => "Database error while validating company preference at position " . ($index + 1),
                "error" => mysqli_error($conn)
            ]);
            exit;
        }
        
        if ($company_check->num_rows === 0) {
            http_response_code(400);
            echo json_encode([
                "status" => false,
                "message" => "Invalid preference at position " . ($index + 1) . ": Company not part of this drive",
                "company_id" => $company_drive_id,
                "drive_id" => $drive_id
            ]);
            exit;
        }
        
        // Check for duplicates
        if (in_array($company_drive_id, $pref_ids)) {
            http_response_code(400);
            echo json_encode([
                "status" => false,
                "message" => "Duplicate company preference at position " . ($index + 1)
            ]);
            exit;
        }
        
        $pref_ids[] = $company_drive_id;
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Get or create day for assignment
        $capacity_per_day = intval($drive['capacity_per_day']);
        $today = date('Y-m-d');
        
        // Find the latest day for this drive
        $latest_day = $conn->query("
            SELECT id, date, day_number, filled_count, capacity 
            FROM campus_drive_days 
            WHERE drive_id = $drive_id 
            ORDER BY day_number DESC 
            LIMIT 1
        ");
        
        $day_id = null;
        $day_number = 1;
        
        if ($latest_day->num_rows > 0) {
            $day_data = $latest_day->fetch_assoc();
            
            // Check if latest day has capacity
            if ($day_data['filled_count'] < $day_data['capacity']) {
                $day_id = $day_data['id'];
                $day_number = $day_data['day_number'];
                
                // Increment filled_count
                $update_result = $conn->query("
                    UPDATE campus_drive_days 
                    SET filled_count = filled_count + 1 
                    WHERE id = $day_id
                ");
                
                if (!$update_result) {
                    throw new Exception("Failed to update day filled_count: " . mysqli_error($conn));
                }
            } else {
                // Create new day
                $day_number = $day_data['day_number'] + 1;
                $new_date = date('Y-m-d', strtotime($drive['start_date'] . " + " . ($day_number - 1) . " days"));
                
                // Make sure date doesn't exceed end_date
                if (strtotime($new_date) > strtotime($drive['end_date'])) {
                    throw new Exception("Drive capacity exceeded. All days are full.");
                }
                
                $day_sql = "INSERT INTO campus_drive_days (drive_id, date, day_number, capacity, filled_count) 
                           VALUES (?, ?, ?, ?, 1)";
                $day_stmt = mysqli_prepare($conn, $day_sql);
                mysqli_stmt_bind_param($day_stmt, "isii", $drive_id, $new_date, $day_number, $capacity_per_day);
                mysqli_stmt_execute($day_stmt);
                $day_id = mysqli_insert_id($conn);
                mysqli_stmt_close($day_stmt);
            }
        } else {
            // First day
            $first_date = $drive['start_date'];
            $day_sql = "INSERT INTO campus_drive_days (drive_id, date, day_number, capacity, filled_count) 
                       VALUES (?, ?, 1, ?, 1)";
            $day_stmt = mysqli_prepare($conn, $day_sql);
            mysqli_stmt_bind_param($day_stmt, "isi", $drive_id, $first_date, $capacity_per_day);
            mysqli_stmt_execute($day_stmt);
            $day_id = mysqli_insert_id($conn);
            mysqli_stmt_close($day_stmt);
        }

        // Insert application
        $sql = "INSERT INTO campus_applications (
                    student_id, drive_id, 
                    pref1_company_id, pref2_company_id, pref3_company_id,
                    pref4_company_id, pref5_company_id, pref6_company_id,
                    assigned_day_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiiiiii", 
            $student_id, $drive_id,
            $pref_ids[0], $pref_ids[1], $pref_ids[2],
            $pref_ids[3], $pref_ids[4], $pref_ids[5],
            $day_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to create application: " . mysqli_error($conn));
        }
        
        $application_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Fetch created application with details
        $result = $conn->query("
            SELECT 
                ca.*,
                cdd.date as assigned_date,
                cdd.day_number
            FROM campus_applications ca
            LEFT JOIN campus_drive_days cdd ON ca.assigned_day_id = cdd.id
            WHERE ca.id = $application_id
        ");
        $application = $result->fetch_assoc();
        
        // Get preference company names
        $pref_companies = [];
        for ($i = 1; $i <= 6; $i++) {
            $pref_id = $application["pref{$i}_company_id"];
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
        
        $application['assigned_day'] = "Day " . $application['day_number'];
        $application['preferences'] = $pref_companies;
        
        http_response_code(201);
        echo json_encode([
            "status" => true,
            "message" => "Application submitted successfully",
            "data" => $application
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }

} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($conn) && mysqli_get_server_info($conn)) {
        @mysqli_rollback($conn);
    }
    
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error submitting application",
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
    error_log("Apply to Drive Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
} catch (Error $e) {
    // Catch PHP 7+ errors
    if (isset($conn) && mysqli_get_server_info($conn)) {
        @mysqli_rollback($conn);
    }
    
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Fatal error submitting application",
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
    error_log("Apply to Drive Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>

