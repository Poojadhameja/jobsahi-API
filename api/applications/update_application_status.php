<?php
// update_application_status.php - Update application status (Admin or Recruiter access, with admin_action visibility)
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

// ✅ Authenticate JWT and allow admin and recruiter roles
$decoded = authenticateJWT(['admin', 'recruiter']);
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// Get application ID from URL
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid application ID"
    ]);
    exit();
}

// Get PUT data
$data = json_decode(file_get_contents("php://input"), true);
$new_status = isset($data['status']) ? $data['status'] : '';

// Validate status
$valid_statuses = ['pending', 'shortlisted', 'rejected', 'selected'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid status. Valid statuses are: " . implode(', ', $valid_statuses)
    ]);
    exit();
}

try {
    // ✅ Check if application exists and get job + visibility details
    $check_sql = "
        SELECT a.id, a.job_id, a.admin_action, j.recruiter_id
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.id = ?
          AND (
                (a.admin_action = 'approved') 
                OR (a.admin_action = 'pending' AND ? = 'admin')
              )
    ";

    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $application_id, $user_role);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Application not found or access denied"
        ]);
        exit();
    }

    $application = $result->fetch_assoc();

    // ✅ Authorization: Admin can update any application, Recruiter only their own job applications
    if ($user_role !== 'admin' && $application['recruiter_id'] != $user_id) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: You can only update applications for your own job postings"
        ]);
        exit();
    }

    // ✅ Update application status
    $update_stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_status, $application_id);

    if ($update_stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Application status updated successfully",
            "application_id" => $application_id,
            "new_status" => $new_status
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update application status",
            "error" => $update_stmt->error
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
