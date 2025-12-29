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

$application_id = intval($input['application_id']);
$interview_date = isset($input['interview_date']) ? mysqli_real_escape_string($conn, $input['interview_date']) : null;
$interview_time = isset($input['interview_time']) ? mysqli_real_escape_string($conn, $input['interview_time']) : null;
$interview_venue = isset($input['interview_venue']) ? mysqli_real_escape_string($conn, $input['interview_venue']) : null;

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

    // Build update query
    $updates = [];
    $params = [];
    $types = "";

    if ($interview_date !== null) {
        $updates[] = "interview_date = ?";
        $params[] = $interview_date;
        $types .= "s";
    }

    if ($interview_time !== null) {
        $updates[] = "interview_time = ?";
        $params[] = $interview_time;
        $types .= "s";
    }

    if ($interview_venue !== null) {
        $updates[] = "interview_venue = ?";
        $params[] = $interview_venue;
        $types .= "s";
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "At least one interview field (date, time, venue) must be provided"
        ]);
        exit;
    }

    $sql = "UPDATE campus_applications SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $application_id;
    $types .= "i";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
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
            "message" => "Interview details assigned successfully",
            "data" => $application
        ]);
    } else {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to assign interview: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error assigning interview details",
        "error" => $e->getMessage()
    ]);
}
?>

