<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['drive_id', 'company_id'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Missing required field: $field"
        ]);
        exit;
    }
}

try {
    $drive_id = intval($input['drive_id']);
    $company_id = intval($input['company_id']);
    $job_roles = isset($input['job_roles']) ? json_encode($input['job_roles']) : null;
    $criteria = isset($input['criteria']) ? json_encode($input['criteria']) : null;
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

    // Check if company exists
    $company_check = $conn->query("SELECT id, company_name FROM recruiter_profiles WHERE id = $company_id");
    if ($company_check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Company not found"
        ]);
        exit;
    }

    // Check if company already added to this drive
    $existing = $conn->query("SELECT id FROM campus_drive_companies WHERE drive_id = $drive_id AND company_id = $company_id");
    if ($existing->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Company already added to this drive"
        ]);
        exit;
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
                rp.company_logo as logo
            FROM campus_drive_companies cdc
            LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
            WHERE cdc.id = $id
        ");
        $company = $result->fetch_assoc();
        
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
    echo json_encode([
        "status" => false,
        "message" => "Error adding company to drive",
        "error" => $e->getMessage()
    ]);
}
?>

