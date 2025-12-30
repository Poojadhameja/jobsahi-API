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
        // Handle manual company name/location updates
        $criteria = is_array($input['criteria']) ? $input['criteria'] : json_decode($input['criteria'], true);
        if (!is_array($criteria)) {
            $criteria = [];
        }
        
        // If manual company name/location provided, add to criteria
        if (isset($input['company_name']) && !isset($input['company_id'])) {
            $criteria['manual_company_name'] = trim($input['company_name']);
        }
        if (isset($input['company_location']) && !isset($input['company_id'])) {
            $criteria['manual_company_location'] = trim($input['company_location']);
        }
        
        $updates[] = "criteria = ?";
        $params[] = json_encode($criteria);
        $types .= "s";
    } else if (isset($input['company_name']) && !isset($input['company_id'])) {
        // Manual entry - update criteria with company name/location
        $existing = $conn->query("SELECT criteria FROM campus_drive_companies WHERE id = $id");
        if ($existing && $existing->num_rows > 0) {
            $row = $existing->fetch_assoc();
            $criteria = json_decode($row['criteria'], true);
            if (!is_array($criteria)) {
                $criteria = [];
            }
            $criteria['manual_company_name'] = trim($input['company_name']);
            if (isset($input['company_location'])) {
                $criteria['manual_company_location'] = trim($input['company_location']);
            }
            $updates[] = "criteria = ?";
            $params[] = json_encode($criteria);
            $types .= "s";
        }
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
                rp.company_logo as logo,
                rp.location as company_location
            FROM campus_drive_companies cdc
            LEFT JOIN recruiter_profiles rp ON cdc.company_id = rp.id
            WHERE cdc.id = $id
        ");
        $company = $result->fetch_assoc();
        
        // If manual entry, override with criteria data
        $criteria_data = json_decode($company['criteria'], true);
        if (is_array($criteria_data) && isset($criteria_data['manual_company_name'])) {
            $company['company_name'] = $criteria_data['manual_company_name'];
            if (isset($criteria_data['manual_company_location'])) {
                $company['company_location'] = $criteria_data['manual_company_location'];
            }
        }
        
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

