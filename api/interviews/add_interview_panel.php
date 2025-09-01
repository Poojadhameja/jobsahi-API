<?php
// add_interview_panel.php - Add interview panel member/feedback (Admin, Recruiter access)
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

// Get interview ID from URL parameter
$interview_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($interview_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid interview ID"
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$panelist_name = isset($data['panelist_name']) ? trim($data['panelist_name']) : '';
$feedback = isset($data['feedback']) ? trim($data['feedback']) : '';
$rating = isset($data['rating']) ? floatval($data['rating']) : null;

// Validate required fields
if (empty($panelist_name)) {
    echo json_encode([
        "status" => false,
        "message" => "Panelist name is required"
    ]);
    exit();
}

try {
    // Check if interview exists and belongs to recruiter (if recruiter role)
    if ($decoded['role'] === 'recruiter') {
        $check_stmt = $conn->prepare("SELECT i.id FROM interviews i 
                                     JOIN applications a ON i.application_id = a.id
                                     JOIN jobs j ON a.job_id = j.id 
                                     WHERE i.id = ? AND j.recruiter_id = ?");
        $check_stmt->bind_param("ii", $interview_id, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Interview not found or access denied"
            ]);
            exit();
        }
    }

    // Check if panelist already exists for this interview
    $exists_stmt = $conn->prepare("SELECT id FROM interview_panel WHERE interview_id = ? AND panelist_name = ?");
    $exists_stmt->bind_param("is", $interview_id, $panelist_name);
    $exists_stmt->execute();
    $exists_result = $exists_stmt->get_result();

    if ($exists_result->num_rows > 0) {
        // Update existing panelist record
        $update_stmt = $conn->prepare("UPDATE interview_panel 
                                       SET feedback = ?, rating = ?, created_at = NOW() 
                                       WHERE interview_id = ? AND panelist_name = ?");
        $update_stmt->bind_param("sdiss", $feedback, $rating, $interview_id, $panelist_name);
        
        if ($update_stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Interview panelist feedback updated successfully"
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to update panelist feedback",
                "error" => $update_stmt->error
            ]);
        }
    } else {
        // Insert new panelist record
        $stmt = $conn->prepare("INSERT INTO interview_panel (interview_id, panelist_name, feedback, rating, created_at)
                                VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("issd", $interview_id, $panelist_name, $feedback, $rating);
        
        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Interview panelist added successfully",
                "panel_id" => $stmt->insert_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to add panelist",
                "error" => $stmt->error
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
