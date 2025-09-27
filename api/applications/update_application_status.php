<?php
// update_application_status.php - Update application status (Admin or Recruiter access)
require_once '../cors.php';

// ✅ Authenticate JWT and allow admin and recruiter roles
$decoded = authenticateJWT(['admin', 'recruiter']);
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// Application ID from URL
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($application_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid application ID"
    ]);
    exit();
}

// Get JSON body (PUT/POST)
$data = json_decode(file_get_contents("php://input"), true);
$new_status = isset($data['status']) ? strtolower(trim($data['status'])) : '';

// ✅ Allowed status values
$valid_statuses = ['applied', 'shortlisted', 'rejected', 'selected'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid status. Allowed: " . implode(', ', $valid_statuses)
    ]);
    exit();
}

try {
    // ✅ Check application exists + fetch recruiter_id
    $check_sql = "
        SELECT a.id, a.job_id, j.recruiter_id
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.id = ?
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $application_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Application not found"
        ]);
        exit();
    }

    $application = $result->fetch_assoc();

    // ✅ Authorization: Admin → sab update kar sakta hai, Recruiter → sirf apni jobs ke applications
    if ($user_role !== 'admin' && $application['recruiter_id'] != $user_id) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: You can only update applications for your own job postings"
        ]);
        exit();
    }

    // ✅ Update status
    $update_sql = "UPDATE applications SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $application_id);

    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
        echo json_encode([
            "status" => true,
            "message" => "Application status updated successfully",
            "application_id" => $application_id,
            "new_status" => $new_status,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "No changes made (maybe already $new_status)"
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
