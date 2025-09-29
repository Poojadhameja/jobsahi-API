<?php
// schedule_interview.php - Schedule or Update interview for candidate (Admin, Recruiter access with role-based visibility)
require_once '../cors.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter']); 
$user_id   = $decoded['user_id'];
$user_role = strtolower($decoded['role'] ?? '');

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
$mode         = isset($data['mode']) ? $data['mode'] : 'online'; // online, offline, phone
$location     = isset($data['location']) ? $data['location'] : '';
$status       = isset($data['status']) ? $data['status'] : 'scheduled';
$feedback     = isset($data['feedback']) ? $data['feedback'] : '';

// Validate required fields
if (empty($scheduled_at)) {
    echo json_encode([
        "status" => false,
        "message" => "Scheduled date and time are required"
    ]);
    exit();
}

try {
    // ✅ Visibility filter using admin_action
    if ($user_role === 'admin') {
        // Admin can see all applications
        $check_sql = "SELECT a.id, a.admin_action 
                      FROM applications a
                      WHERE a.id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $application_id);
    } else {
        // Recruiter → only if admin_action = 'approved'
        $check_sql = "SELECT a.id, a.admin_action 
                      FROM applications a
                      WHERE a.id = ? AND a.admin_action = 'approved'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $application_id);
    }

    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Application not found or access denied (based on admin_action)"
        ]);
        exit();
    }

    // Extra recruiter ownership check
    if ($user_role === 'recruiter') {
        $check_recruiter = $conn->prepare("SELECT a.id 
                                          FROM applications a 
                                          JOIN jobs j ON a.job_id = j.id 
                                          WHERE a.id = ? AND j.recruiter_id = ?");
        $check_recruiter->bind_param("ii", $application_id, $user_id);
        $check_recruiter->execute();
        $rec_result = $check_recruiter->get_result();

        if ($rec_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Recruiter does not own this application"
            ]);
            exit();
        }
    }

    // ✅ Check if interview already exists
    $check_interview = $conn->prepare("SELECT id FROM interviews WHERE application_id = ?");
    $check_interview->bind_param("i", $application_id);
    $check_interview->execute();
    $interview_result = $check_interview->get_result();

    if ($interview_result->num_rows > 0) {
        // 🔄 Update existing interview
        $interview_row = $interview_result->fetch_assoc();
        $interview_id  = $interview_row['id'];

        $stmt = $conn->prepare("UPDATE interviews 
                                SET scheduled_at = ?, mode = ?, location = ?, status = ?, feedback = ?, modified_at = NOW() 
                                WHERE id = ?");
        $stmt->bind_param("sssssi", $scheduled_at, $mode, $location, $status, $feedback, $interview_id);
        $stmt->execute();

        echo json_encode([
            "status" => true,
            "message" => "Interview updated successfully",
            "interview_id" => $interview_id
        ]);
    } else {
        // 🆕 Insert new interview
        $stmt = $conn->prepare("INSERT INTO interviews (application_id, scheduled_at, mode, location, status, feedback, created_at, modified_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("isssss", $application_id, $scheduled_at, $mode, $location, $status, $feedback);
        $stmt->execute();

        echo json_encode([
            "status" => true,
            "message" => "Interview scheduled successfully",
            "interview_id" => $stmt->insert_id
        ]);
    }

    // ✅ Always update application status
    $update_stmt = $conn->prepare("UPDATE applications SET status = 'interview_scheduled' WHERE id = ?");
    $update_stmt->bind_param("i", $application_id);
    $update_stmt->execute();

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
