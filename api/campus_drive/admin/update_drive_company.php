<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required field
if (empty($input['id'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Missing required field: id"
    ]);
    exit;
}

$id = intval($input['id']);

try {
    // Check if record exists
    $check = $conn->query("SELECT id FROM campus_drive_companies WHERE id = $id");
    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Company drive record not found"
        ]);
        exit;
    }

    // Build update query
    $updates = [];
    $params = [];
    $types = "";

    if (isset($input['job_roles'])) {
        $updates[] = "job_roles = ?";
        $params[] = json_encode($input['job_roles']);
        $types .= "s";
    }

    if (isset($input['criteria'])) {
        $updates[] = "criteria = ?";
        $params[] = json_encode($input['criteria']);
        $types .= "s";
    }

    if (isset($input['vacancies'])) {
        $updates[] = "vacancies = ?";
        $params[] = intval($input['vacancies']);
        $types .= "i";
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "No valid fields to update"
        ]);
        exit;
    }

    $sql = "UPDATE campus_drive_companies SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Fetch updated record
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
        
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "Company drive record updated successfully",
            "data" => $company
        ]);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to update company: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error updating company drive record",
        "error" => $e->getMessage()
    ]);
}
?>

