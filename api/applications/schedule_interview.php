<?php
// schedule_interview.php - Schedule interview for candidate (Admin, Recruiter access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter']); // returns array
$user_id = $decoded['user_id']; // Extract user_id from JWT token

// Get application ID from URL parameter
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid application ID"
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$scheduled_at = isset($data['scheduled_at']) ? $data['scheduled_at'] : '';
$mode = isset($data['mode']) ? $data['mode'] : 'online'; // online, offline, phone
$location = isset($data['location']) ? $data['location'] : '';
$status = isset($data['status']) ? $data['status'] : 'scheduled';
$feedback = isset($data['feedback']) ? $data['feedback'] : '';

// Validate required fields
if (empty($scheduled_at)) {
    echo json_encode([
        "status" => false,
        "message" => "Scheduled date and time are required"
    ]);
    exit();
}

try {
    // Check if application exists and belongs to recruiter (if recruiter role)
    if ($decoded['role'] === 'recruiter') {
        $check_stmt = $conn->prepare("SELECT a.id FROM applications a 
                                     JOIN jobs j ON a.job_id = j.id 
                                     WHERE a.id = ? AND j.recruiter_id = ?");
        $check_stmt->bind_param("ii", $application_id, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Application not found or access denied"
            ]);
            exit();
        }
    }

    // Insert interview record
    $stmt = $conn->prepare("INSERT INTO interviews (application_id, scheduled_at, mode, location, status, feedback, created_at, modified_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("isssss", $application_id, $scheduled_at, $mode, $location, $status, $feedback);
    
    if ($stmt->execute()) {
        // Update application status to 'interview_scheduled'
        $update_stmt = $conn->prepare("UPDATE applications SET status = 'interview_scheduled' WHERE id = ?");
        $update_stmt->bind_param("i", $application_id);
        $update_stmt->execute();
        
        echo json_encode([
            "status" => true,
            "message" => "Interview scheduled successfully",
            "interview_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to schedule interview",
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