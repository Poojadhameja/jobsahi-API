<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, return as JSON
ini_set('log_errors', 1);

require_once '../../cors.php';
require_once '../../db.php';

// --- ROBUST SCHEMA FIX START ---
// Automatically ensure the database allows NULL for preference columns
function ensure_preferences_nullable($conn) {
    try {
        // Check if pref2_company_id is nullable
        $check = $conn->query("SHOW COLUMNS FROM campus_applications LIKE 'pref2_company_id'");
        if ($check && $check->num_rows > 0) {
            $row = $check->fetch_assoc();
            // If Null is 'NO', it means NOT NULL, so we need to fix it
            if (isset($row['Null']) && strtoupper($row['Null']) === 'NO') {
                error_log("Fixing schema: altering campus_applications to allow NULL preferences...");
                // We use a single query to modify all at once
                $conn->query("ALTER TABLE `campus_applications` 
                    MODIFY `pref2_company_id` INT(11) NULL,
                    MODIFY `pref3_company_id` INT(11) NULL,
                    MODIFY `pref4_company_id` INT(11) NULL,
                    MODIFY `pref5_company_id` INT(11) NULL,
                    MODIFY `pref6_company_id` INT(11) NULL");
            }
        }
    } catch (Exception $e) {
        // Log but don't stop execution
        error_log("Schema fix warning: " . $e->getMessage());
    }
}
// Run the check once per request (overhead is minimal: 1 SHOW COLUMNS query)
ensure_preferences_nullable($conn);
// --- ROBUST SCHEMA FIX END ---

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
    if (empty($input['preferences']) || !is_array($input['preferences']) || count($input['preferences']) < 1 || count($input['preferences']) > 6) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Minimum 1 and maximum 6 company preferences are allowed",
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
    $existing = $conn->query("SELECT * FROM campus_applications WHERE drive_id = $drive_id AND student_id = $student_id");
    
    $existing_app_id = null;
    $existing_prefs_set = [];
    $next_slot_index = 1; // 1-based index for next available slot
    
    if ($existing && $existing->num_rows > 0) {
        $row = $existing->fetch_assoc();
        $existing_app_id = $row['id'];
        
        // Collect existing preferences
        for ($i = 1; $i <= 6; $i++) {
            if (!empty($row["pref{$i}_company_id"])) {
                $existing_prefs_set[] = intval($row["pref{$i}_company_id"]);
            }
        }
        
        // Check if already full
        if (count($existing_prefs_set) >= 6) {
             http_response_code(200); // OK, but no action needed or client should have prevented this
             echo json_encode([
                 "status" => true,
                 "message" => "You have already applied to the maximum number of companies (6).",
                 "data" => $row,
                 "limit_reached" => true
             ]);
             exit;
        }
        
        $next_slot_index = count($existing_prefs_set) + 1;
    }

    // Validate all preferences are valid company IDs for this drive
    // Note: company_id in request refers to campus_drive_companies.id (not recruiter_profiles.id)
    $new_prefs_to_add = [];
    
    foreach ($preferences as $index => $pref) {
        if (empty($pref['company_id'])) {
            continue; // Skip empty
        }

        $company_drive_id = intval($pref['company_id']); 

        // Check for duplicates in EXISTING application
        if (in_array($company_drive_id, $existing_prefs_set)) {
            continue; // Already applied, skip
        }
        
        // Check for duplicates in NEW batch
        if (in_array($company_drive_id, $new_prefs_to_add)) {
            continue; // Duplicate in request, skip
        }

        // Check if this company is part of this drive
        $company_check = $conn->query("
            SELECT id FROM campus_drive_companies 
            WHERE id = $company_drive_id AND drive_id = $drive_id
        ");

        if (!$company_check || $company_check->num_rows === 0) {
             // Invalid company, maybe return error or just skip?
             // Returning error is safer to alert user
             http_response_code(400);
             echo json_encode([
                "status" => false,
                "message" => "Invalid company preference: Company ID $company_drive_id is not part of this drive"
             ]);
             exit;
        }

        $new_prefs_to_add[] = $company_drive_id;
    }

    // Check capacity constraint
    $total_after_add = count($existing_prefs_set) + count($new_prefs_to_add);
    if ($total_after_add > 6) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "You can select maximum 6 companies. You already have " . count($existing_prefs_set) . " and tried to add " . count($new_prefs_to_add) . "."
        ]);
        exit;
    }
    
    if (empty($new_prefs_to_add) && $existing_app_id) {
        // Nothing new to add
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "No new companies selected.",
            "data" => $row
        ]);
        exit;
    }

    // If we have an existing application, UPDATE it
    if ($existing_app_id) {
        // Construct UPDATE query
        $update_clauses = [];
        $current_slot = $next_slot_index;
        
        foreach ($new_prefs_to_add as $new_company_id) {
            $update_clauses[] = "pref{$current_slot}_company_id = $new_company_id";
            $current_slot++;
        }
        
        if (!empty($update_clauses)) {
            $sql = "UPDATE campus_applications SET " . implode(", ", $update_clauses) . " WHERE id = $existing_app_id";
            if (!$conn->query($sql)) {
                throw new Exception("Database error updating application: " . mysqli_error($conn));
            }
        }
        
        // Return updated application
        $result = $conn->query("
            SELECT 
                ca.*,
                cdd.date as assigned_date,
                cdd.day_number
            FROM campus_applications ca
            LEFT JOIN campus_drive_days cdd ON ca.assigned_day_id = cdd.id
            WHERE ca.id = $existing_app_id
        ");
        $application = $result->fetch_assoc();
        
         // Get preference company names (Duplicate logic, could be refactored)
        $pref_companies = [];
        for ($i = 1; $i <= 6; $i++) {
            $pref_id = $application["pref{$i}_company_id"];
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
        $application['assigned_day'] = $application['day_number'] ? "Day " . $application['day_number'] : null;
        $application['preferences'] = $pref_companies;

        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "Application updated successfully",
            "data" => $application
        ]);
        exit;
    }

    // NEW APPLICATION LOGIC (Only if existing_app_id is null)
    // Combine new prefs into the array structure expected by the original code
    $pref_ids = $new_prefs_to_add;

    // Fill remaining preferences with NULL up to 6
    while (count($pref_ids) < 6) {
        $pref_ids[] = null;
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
        mysqli_stmt_bind_param(
            $stmt,
            "iiiiiiiii",
            $student_id,
            $drive_id,
            $pref_ids[0],
            $pref_ids[1],
            $pref_ids[2],
            $pref_ids[3],
            $pref_ids[4],
            $pref_ids[5],
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
            // Fix: Check if pref_id is not null before querying
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

        $application['assigned_day'] = "Day " . $application['day_number'];
        $application['preferences'] = $pref_companies;

        http_response_code(201);
        echo json_encode([
            "status" => true,
            "message" => "Application submitted successfully",
            "data" => $application
        ]);
        exit;


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
