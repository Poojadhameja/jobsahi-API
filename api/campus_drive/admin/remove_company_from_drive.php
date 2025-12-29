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

    // Check if any applications exist with this company
    $app_check = $conn->query("
        SELECT COUNT(*) as count FROM campus_applications 
        WHERE pref1_company_id = $id 
           OR pref2_company_id = $id 
           OR pref3_company_id = $id
           OR pref4_company_id = $id
           OR pref5_company_id = $id
           OR pref6_company_id = $id
    ");
    $app_count = $app_check->fetch_assoc()['count'];
    
    if ($app_count > 0) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Cannot remove company: $app_count application(s) have this company as preference"
        ]);
        exit;
    }

    // Delete company from drive
    $sql = "DELETE FROM campus_drive_companies WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "message" => "Company removed from campus drive successfully"
        ]);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to remove company: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error removing company from drive",
        "error" => $e->getMessage()
    ]);
}
?>


