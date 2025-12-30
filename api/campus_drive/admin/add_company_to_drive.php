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
    $drive_check = $conn->query("SELECT id FROM campus_drives WHERE id = $drive_id");
    if ($drive_check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Campus drive not found"
        ]);
        exit;
    }

    // Handle manual company entry - SIMPLE APPROACH
    // Manual entry = company_name hai but company_id nahi hai
    if ($company_name && !$company_id) {
        // Step 1: Get system manual company ID (create if doesn't exist - only once)
        $sys_company_query = "SELECT id FROM recruiter_profiles WHERE user_id IS NULL AND company_name = 'Manual Company (System)' LIMIT 1";
        $sys_result = $conn->query($sys_company_query);
        
        if ($sys_result && $sys_result->num_rows > 0) {
            $sys_row = $sys_result->fetch_assoc();
            $company_id = intval($sys_row['id']);
        } else {
            // Create system entry (only first time)
            $create_sys = "INSERT INTO recruiter_profiles (user_id, company_name, location) VALUES (NULL, 'Manual Company (System)', 'N/A')";
            if ($conn->query($create_sys)) {
                $company_id = mysqli_insert_id($conn);
            } else {
                throw new Exception("System error: " . mysqli_error($conn));
            }
        }
        
        // Step 2: Check duplicate - same name in same drive?
        $dup_check = $conn->query("SELECT id, criteria FROM campus_drive_companies WHERE drive_id = $drive_id AND company_id = $company_id");
        if ($dup_check) {
            while ($dup_row = $dup_check->fetch_assoc()) {
                $dup_criteria = json_decode($dup_row['criteria'], true);
                if (isset($dup_criteria['manual_company_name']) && 
                    strtolower(trim($dup_criteria['manual_company_name'])) === strtolower(trim($company_name))) {
                    http_response_code(400);
                    echo json_encode(["status" => false, "message" => "Company already added"]);
                    exit;
                }
            }
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
        $company_check = $conn->query("SELECT id, company_name FROM recruiter_profiles WHERE id = $company_id");
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
        $existing_check = $conn->query("SELECT id FROM campus_drive_companies WHERE drive_id = $drive_id AND company_id = $company_id");
        if ($existing_check && $existing_check->num_rows > 0) {
            http_response_code(400);
            echo json_encode([
                "status" => false,
                "message" => "Company already added to this drive"
            ]);
            exit;
        }
    }

    // Insert company to drive
    $sql = "INSERT INTO campus_drive_companies (drive_id, company_id, job_roles, criteria, vacancies) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iissi", $drive_id, $company_id, $job_roles, $criteria, $vacancies);
    
    if (mysqli_stmt_execute($stmt)) {
        $id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        
        // Fetch created record with company details
        $result = $conn->query("
            SELECT 
                cdc.*,
                rp.company_name,
                rp.company_logo as logo,
                rp.location as company_location
            FROM campus_drive_companies cdc
            LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
            WHERE cdc.id = $id
        ");
        $company = $result->fetch_assoc();
        
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
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to add company: " . mysqli_error($conn));
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

