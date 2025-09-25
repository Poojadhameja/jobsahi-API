<?php
// update_interview.php - Update/reschedule interview (Admin, Recruiter access) - PUT API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow PUT method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode([
        "status" => false,
        "message" => "Method not allowed"
    ]);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter']); // all possible roles
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// Get interview ID from URL parameter
$interview_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($interview_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid interview ID"
    ]);
    exit();
}

// Get PUT data
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
    // Role-based visibility query for admin_action
    if ($user_role === 'admin') {
        // Admin sees all, including 'pending'
        $check_sql = "SELECT * FROM interviews WHERE id = ? AND (admin_action = 'pending' OR admin_action = 'approved')";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $interview_id);
    } else {
        // Non-admin (recruiter, institute, student) sees only approved interviews
        $check_sql = "SELECT * FROM interviews WHERE id = ? AND admin_action = 'approved'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $interview_id);
    }

    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Interview not found or access denied"
        ]);
        exit();
    }

    // Additional check: recruiters can only update their own interviews
    if ($user_role === 'recruiter') {
        $row = $result->fetch_assoc();
        // Join with jobs table to check recruiter ownership
        $verify_stmt = $conn->prepare("SELECT j.id FROM interviews i 
                                       JOIN applications a ON i.application_id = a.id
                                       JOIN jobs j ON a.job_id = j.id 
                                       WHERE i.id = ? AND j.recruiter_id = ?");
        $verify_stmt->bind_param("ii", $interview_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        if ($verify_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Access denied for this interview"
            ]);
            exit();
        }
    }

    // Update interview record
    $stmt = $conn->prepare("UPDATE interviews 
                            SET scheduled_at = ?, mode = ?, location = ?, status = ?, feedback = ?, modified_at = NOW()
                            WHERE id = ?");
    $stmt->bind_param("sssssi", $scheduled_at, $mode, $location, $status, $feedback, $interview_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Interview updated successfully",
                "interview_id" => $interview_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "No changes made or interview not found"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update interview",
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
