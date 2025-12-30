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
    $check = $conn->query("SELECT id FROM campus_applications WHERE id = $application_id");
    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Application not found"
        ]);
        exit;
    }

    // Update status
    $sql = "UPDATE campus_applications SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $status, $application_id);
    
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

