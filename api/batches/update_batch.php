<?php
// update_batch.php - Update batch details (Admin, Recruiter access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate JWT and allow only admin and recruiter
$decoded = authenticateJWT(['admin', 'recruiter']);
$user_role = $decoded['role'] ?? null;

// Get batch ID from query parameter
if (!isset($_GET['batch_id']) || !is_numeric($_GET['batch_id'])) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid batch ID"
    ]);
    exit();
}

$batch_id = intval($_GET['batch_id']);

// Get PUT data
$data = json_decode(file_get_contents("php://input"), true);

$course_id     = $data['course_id'] ?? null;
$name          = $data['name'] ?? '';
$start_date    = $data['start_date'] ?? null;
$end_date      = $data['end_date'] ?? null;
$instructor_id = $data['instructor_id'] ?? null;
$admin_action  = $data['admin_action'] ?? 'pending';

try {
    // Check if batch exists
    $check_stmt = $conn->prepare("SELECT id, admin_action FROM batches WHERE id = ?");
    $check_stmt->bind_param("i", $batch_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Batch not found"
        ]);
        exit();
    }

    $batch = $result->fetch_assoc();

    // Only admin can change 'admin_action' from 'pending' to 'approved'
    if (isset($data['admin_action']) && $user_role !== 'admin') {
        echo json_encode([
            "status" => false,
            "message" => "Only admin can update admin_action"
        ]);
        exit();
    }

    // Update batch
    $stmt = $conn->prepare("
        UPDATE batches SET 
            course_id = ?, 
            name = ?, 
            start_date = ?, 
            end_date = ?, 
            instructor_id = ?, 
            admin_action = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "isssisi",
        $course_id,
        $name,
        $start_date,
        $end_date,
        $instructor_id,
        $admin_action,
        $batch_id
    );

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Batch updated successfully",
            "batch_id" => $batch_id
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update batch",
            "error" => $stmt->error
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
