<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

// Get request data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// Debug: Log if JSON decode failed
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    error_log("Raw input: " . substr($raw_input, 0, 500));
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

// Either company_id or company_name must be provided
// Check for valid company_id (must be positive integer)
$has_company_id = false;
if (isset($input['company_id'])) {
    $company_id_val = $input['company_id'];
    // Check if it's a valid positive integer
    if (is_numeric($company_id_val) && intval($company_id_val) > 0) {
        $has_company_id = true;
    }
}

// Check for valid company_name (non-empty string)
$has_company_name = false;
if (isset($input['company_name']) && $input['company_name'] !== null) {
    $company_name_val = trim($input['company_name']);
    if (!empty($company_name_val)) {
        $has_company_name = true;
    }
}

if (!$has_company_id && !$has_company_name) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Either company_id or company_name must be provided",
        "debug" => [
            "received_company_id" => isset($input['company_id']) ? $input['company_id'] : 'not set',
            "received_company_name" => isset($input['company_name']) ? $input['company_name'] : 'not set',
            "has_company_id" => $has_company_id,
            "has_company_name" => $has_company_name
        ]
    ]);
    exit;
}

try {
    $drive_id = intval($input['drive_id']);
    // Handle company_id - can be null for manual entries
    $company_id = null;
    if (isset($input['company_id']) && $input['company_id'] !== null && $input['company_id'] !== '' && $input['company_id'] !== 0) {
        $company_id = intval($input['company_id']);
    }
    
    $company_name = isset($input['company_name']) && $input['company_name'] !== null ? trim($input['company_name']) : null;
    $company_location = isset($input['company_location']) && $input['company_location'] !== null ? trim($input['company_location']) : null;
    $job_roles = isset($input['job_roles']) ? json_encode($input['job_roles']) : null;
    
    // Handle criteria - can be object or already JSON string
    $criteria = null;
    if (isset($input['criteria'])) {
        if (is_string($input['criteria'])) {
            // Already a JSON string, decode and re-encode to ensure it's valid
            $criteria_array = json_decode($input['criteria'], true);
            if ($criteria_array === null && json_last_error() !== JSON_ERROR_NONE) {
                // Invalid JSON, treat as empty object
                $criteria_array = [];
            }
            $criteria = json_encode($criteria_array ?: []);
        } else {
            // It's an array/object, encode it
            $criteria = json_encode($input['criteria'] ?: []);
        }
    } else {
        $criteria = json_encode([]);
    }
    
    $vacancies = isset($input['vacancies']) ? intval($input['vacancies']) : 0;

    // Check if drive exists
    $drive_check_stmt = mysqli_prepare($conn, "SELECT id FROM campus_drives WHERE id = ?");
    mysqli_stmt_bind_param($drive_check_stmt, "i", $drive_id);
    mysqli_stmt_execute($drive_check_stmt);
    $drive_check = mysqli_stmt_get_result($drive_check_stmt);
    if ($drive_check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Campus drive not found"
        ]);
        exit;
    }

    // Handle manual company entry - Create unique company_id for each manual entry
    // Manual entry = company_name hai but company_id nahi hai
    if ($company_name && !$company_id) {
        // Step 1: Check if this exact manual company name already exists in this drive
        // We need to check all companies in this drive to see if any have this manual_company_name
        $check_manual_stmt = mysqli_prepare($conn, "SELECT id, criteria FROM campus_drive_companies WHERE drive_id = ?");
        mysqli_stmt_bind_param($check_manual_stmt, "i", $drive_id);
        mysqli_stmt_execute($check_manual_stmt);
        $check_manual_result = mysqli_stmt_get_result($check_manual_stmt);
        
        if ($check_manual_result) {
            while ($check_row = $check_manual_result->fetch_assoc()) {
                $check_criteria = json_decode($check_row['criteria'], true);
                if (isset($check_criteria['manual_company_name']) && 
                    strtolower(trim($check_criteria['manual_company_name'])) === strtolower(trim($company_name))) {
                    mysqli_stmt_close($check_manual_stmt);
                    http_response_code(400);
                    echo json_encode([
                        "status" => false, 
                        "message" => "Company '" . htmlspecialchars($company_name) . "' is already added to this drive"
                    ]);
                    exit;
                }
            }
        }
        mysqli_stmt_close($check_manual_stmt);
        
        // Step 2: Create a unique company entry for this manual company
        // Each manual entry gets its own recruiter_profile entry
        $manual_company_name_db = 'Manual Entry: ' . mysqli_real_escape_string($conn, $company_name);
        $manual_location_db = $company_location ? mysqli_real_escape_string($conn, $company_location) : 'N/A';
        
        $create_manual_stmt = mysqli_prepare($conn, "INSERT INTO recruiter_profiles (user_id, company_name, location) VALUES (NULL, ?, ?)");
        mysqli_stmt_bind_param($create_manual_stmt, "ss", $manual_company_name_db, $manual_location_db);
        
        if (mysqli_stmt_execute($create_manual_stmt)) {
            $company_id = mysqli_insert_id($conn);
            mysqli_stmt_close($create_manual_stmt);
            
            if (!$company_id || $company_id <= 0) {
                throw new Exception("Failed to create manual company entry");
            }
        } else {
            $error = mysqli_stmt_error($create_manual_stmt);
            mysqli_stmt_close($create_manual_stmt);
            throw new Exception("Failed to create manual company entry: " . $error);
        }
        
        // Validate that company_id is set
        if (!$company_id || $company_id <= 0) {
            throw new Exception("Invalid manual company ID: " . $company_id);
        }
        
        // Step 3: Add manual company details to criteria
        $criteria_obj = json_decode($criteria, true);
        if (!is_array($criteria_obj)) $criteria_obj = [];
        
        $criteria_obj['manual_company_name'] = $company_name;
        if ($company_location) {
            $criteria_obj['manual_company_location'] = $company_location;
        }
        
        $criteria = json_encode($criteria_obj);
        
    } else if ($company_id) {
        // Check if company exists (existing recruiter profile)
        $company_check_stmt = mysqli_prepare($conn, "SELECT id, company_name FROM recruiter_profiles WHERE id = ?");
        mysqli_stmt_bind_param($company_check_stmt, "i", $company_id);
        mysqli_stmt_execute($company_check_stmt);
        $company_check = mysqli_stmt_get_result($company_check_stmt);
        if ($company_check->num_rows === 0) {
            http_response_code(404);
            echo json_encode([
                "status" => false,
                "message" => "Company not found"
            ]);
            exit;
        }
    }

    // Check if company already added to this drive (for regular entries only, manual entries checked above)
    if ($company_id && !$company_name) {
        // Regular entry - check by company_id
        $existing_check_stmt = mysqli_prepare($conn, "SELECT id FROM campus_drive_companies WHERE drive_id = ? AND company_id = ?");
        mysqli_stmt_bind_param($existing_check_stmt, "ii", $drive_id, $company_id);
        mysqli_stmt_execute($existing_check_stmt);
        $existing_check = mysqli_stmt_get_result($existing_check_stmt);
        if ($existing_check && $existing_check->num_rows > 0) {
            http_response_code(400);
            echo json_encode([
                "status" => false,
                "message" => "Company already added to this drive"
            ]);
            exit;
        }
    }

    // Final validation: company_id must be set and valid
    if (!$company_id || $company_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid company ID. Cannot proceed with insertion.",
            "debug" => [
                "company_id" => $company_id,
                "company_name" => $company_name,
                "has_company_id" => isset($input['company_id']),
                "has_company_name" => isset($input['company_name'])
            ]
        ]);
        exit;
    }

    // Insert company to drive
    $sql = "INSERT INTO campus_drive_companies (drive_id, company_id, job_roles, criteria, vacancies) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "iissi", $drive_id, $company_id, $job_roles, $criteria, $vacancies);
    
    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        if (!$id || $id <= 0) {
            throw new Exception("Failed to get inserted record ID");
        }
        
        // Fetch created record with company details
        $fetch_stmt = mysqli_prepare($conn, "
            SELECT 
                cdc.*,
                rp.company_name,
                rp.company_logo as logo,
                rp.location as company_location
            FROM campus_drive_companies cdc
            LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
            WHERE cdc.id = ?
        ");
        mysqli_stmt_bind_param($fetch_stmt, "i", $id);
        mysqli_stmt_execute($fetch_stmt);
        $result = mysqli_stmt_get_result($fetch_stmt);
        
        if (!$result) {
            throw new Exception("Failed to fetch created record: " . mysqli_error($conn));
        }
        
        $company = $result->fetch_assoc();
        
        if (!$company) {
            throw new Exception("Created record not found");
        }
        
        // Manual entry: Use name and location from criteria (not from recruiter profile)
        if ($company_name && !isset($input['company_id'])) {
            $criteria_data = json_decode($company['criteria'], true);
            if (is_array($criteria_data)) {
                if (isset($criteria_data['manual_company_name'])) {
                    $company['company_name'] = $criteria_data['manual_company_name'];
                }
                if (isset($criteria_data['manual_company_location'])) {
                    $company['company_location'] = $criteria_data['manual_company_location'];
                }
            }
        }
        
        http_response_code(201);
        echo json_encode([
            "status" => true,
            "message" => "Company added to campus drive successfully",
            "data" => $company
        ]);
    } else {
        $error_msg = mysqli_stmt_error($stmt);
        $mysql_error = mysqli_error($conn);
        mysqli_stmt_close($stmt);
        
        // Check for duplicate entry error
        if (strpos($mysql_error, "Duplicate entry") !== false && strpos($mysql_error, "unique_drive_company") !== false) {
            http_response_code(400);
            echo json_encode([
                "status" => false,
                "message" => "This company is already added to this drive. Please check the company list.",
                "error" => "Duplicate entry detected"
            ]);
            exit;
        }
        
        throw new Exception("Failed to execute statement: " . $error_msg . " | MySQL Error: " . $mysql_error);
    }

} catch (Exception $e) {
    http_response_code(500);
    // Log error for debugging
    $error_msg = $e->getMessage();
    $error_trace = $e->getTraceAsString();
    error_log("Add Company to Drive Error: " . $error_msg);
    error_log("Stack trace: " . $error_trace);
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    // Also log last MySQL error if any
    if (isset($conn)) {
        $mysql_error = mysqli_error($conn);
        if ($mysql_error) {
            error_log("MySQL Error: " . $mysql_error);
        }
    }
    
    echo json_encode([
        "status" => false,
        "message" => "Error adding company to drive",
        "error" => $error_msg,
        "file" => basename($e->getFile()),
        "line" => $e->getLine(),
        "debug" => [
            "drive_id" => isset($input['drive_id']) ? $input['drive_id'] : 'not set',
            "company_id" => isset($input['company_id']) ? $input['company_id'] : 'not set',
            "company_name" => isset($input['company_name']) ? $input['company_name'] : 'not set',
            "is_manual" => isset($input['company_name']) && !isset($input['company_id'])
        ]
    ]);
}
?>

