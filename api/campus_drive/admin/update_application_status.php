<?php
require_once '../../cors.php';
require_once '../../db.php';

// Admin Only
$decoded = authenticateJWT(['admin']);

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['application_id'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Missing required field: application_id"
    ]);
    exit;
}

if (empty($input['status'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Missing required field: status"
    ]);
    exit;
}

$application_id = intval($input['application_id']);
$status = mysqli_real_escape_string($conn, $input['status']);
$company_id = isset($input['company_id']) ? intval($input['company_id']) : null; // campus_drive_companies.id

// Validate status
if (!in_array($status, ['pending', 'shortlisted', 'rejected', 'selected'])) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Invalid status. Must be one of: pending, shortlisted, rejected, selected"
    ]);
    exit;
}

try {
    // Check if application exists
    $check_sql = "SELECT id FROM campus_applications WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $application_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if ($check_result->num_rows === 0) {
        mysqli_stmt_close($check_stmt);
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Application not found"
        ]);
        exit;
    }
    mysqli_stmt_close($check_stmt);
    
    // If company_id is provided, store company-specific selection
    if ($company_id) {
        // Check if company_selections column exists
        $column_check = $conn->query("SHOW COLUMNS FROM campus_applications LIKE 'company_selections'");
        $column_exists = $column_check && $column_check->num_rows > 0;
        
        if ($column_exists) {
            // Get current company_selections
            $get_sql = "SELECT company_selections FROM campus_applications WHERE id = ?";
            $get_stmt = mysqli_prepare($conn, $get_sql);
            mysqli_stmt_bind_param($get_stmt, "i", $application_id);
            mysqli_stmt_execute($get_stmt);
            $get_result = mysqli_stmt_get_result($get_stmt);
            $app_data = $get_result->fetch_assoc();
            mysqli_stmt_close($get_stmt);
            
            $company_selections = json_decode($app_data['company_selections'] ?? '{}', true);
            if (!is_array($company_selections)) {
                $company_selections = [];
            }
            
            if ($status === 'selected') {
                // Mark as selected for this company
                $company_selections[$company_id] = 'selected';
            } else {
                // Remove selection for this company if status is not selected
                unset($company_selections[$company_id]);
            }
            $company_selections_json = json_encode($company_selections);
            
            // Update company_selections JSON field
            $sql = "UPDATE campus_applications SET company_selections = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $company_selections_json, $application_id);
        } else {
            // Column doesn't exist, just update global status
            $sql = "UPDATE campus_applications SET status = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $status, $application_id);
        }
    } else {
        // Update global status (for backward compatibility)
        $sql = "UPDATE campus_applications SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $application_id);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Fetch updated application
        $result = $conn->query("
            SELECT 
                ca.*,
                u.user_name as student_name,
                u.email as student_email
            FROM campus_applications ca
            LEFT JOIN student_profiles sp ON ca.student_id = sp.id
            LEFT JOIN users u ON sp.user_id = u.id
            WHERE ca.id = $application_id
        ");
        $application = $result->fetch_assoc();
        
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "Application status updated successfully",
            "data" => $application
        ]);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to update application status: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error updating application status",
        "error" => $e->getMessage()
    ]);
}
?>

