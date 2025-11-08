<?php
// schedule_interview.php - Schedule interview for candidate (Admin, Recruiter access)
require_once '../cors.php';

// ✅ Authenticate JWT (admin + recruiter)
$decoded = authenticateJWT(['admin', 'recruiter']); 
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// ✅ Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$application_id = isset($data['application_id']) ? intval($data['application_id']) : 0;
$scheduled_at   = isset($data['scheduled_at']) ? trim($data['scheduled_at']) : '';
$mode           = isset($data['mode']) ? trim($data['mode']) : 'online';
$location       = isset($data['location']) ? trim($data['location']) : '';
$status         = isset($data['status']) ? trim($data['status']) : 'scheduled';
$feedback       = isset($data['feedback']) ? trim($data['feedback']) : '';

if ($application_id <= 0) {
    echo json_encode(["status" => false, "message" => "Missing or invalid application_id"]);
    exit();
}

if (empty($scheduled_at)) {
    echo json_encode(["status" => false, "message" => "Scheduled date and time are required"]);
    exit();
}

try {
    // ✅ Step 1: Validate application exists
    $check_sql = "SELECT id, student_id FROM applications WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $application_id);
    $check_stmt->execute();
    $app_result = $check_stmt->get_result();

    if ($app_result->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Application not found"]);
        exit();
    }

    $app_row = $app_result->fetch_assoc();
    $student_id = intval($app_row['student_id']); // ✅ Extract student_id from applications

    // ✅ Step 2: Recruiter ownership check (only if recruiter)
    if ($user_role === 'recruiter') {
        $rec_profile_stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rec_profile_stmt->bind_param("i", $user_id);
        $rec_profile_stmt->execute();
        $rec_profile_result = $rec_profile_stmt->get_result();

        if ($rec_profile_result->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
            exit();
        }

        $rec_profile_row = $rec_profile_result->fetch_assoc();
        $recruiter_profile_id = $rec_profile_row['id'];

        $check_recruiter = $conn->prepare("
            SELECT a.id 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            WHERE a.id = ? AND j.recruiter_id = ?
        ");
        $check_recruiter->bind_param("ii", $application_id, $recruiter_profile_id);
        $check_recruiter->execute();
        $rec_result = $check_recruiter->get_result();

        if ($rec_result->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Recruiter does not own this application"]);
            exit();
        }
    }

    // ✅ Step 3: Insert interview record
    $stmt = $conn->prepare("
        INSERT INTO interviews (
            application_id, scheduled_at, mode, location, status, feedback, created_at, modified_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param("isssss", $application_id, $scheduled_at, $mode, $location, $status, $feedback);

    if ($stmt->execute()) {
        $interview_id = $stmt->insert_id;

        // ✅ Step 4: Update only interview_id and status in applications
        $update_stmt = $conn->prepare("
            UPDATE applications 
            SET status = 'shortlisted',
                interview_id = ?,
                modified_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->bind_param("ii", $interview_id, $application_id);
        $update_stmt->execute();

        // ✅ Step 5: Response includes student_id also (without adding to DB)
        echo json_encode([
            "status" => true,
            "message" => "Interview scheduled successfully",
            "interview_id" => $interview_id,
            "student_id" => $student_id   // 🟢 Added student_id from applications table
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to schedule interview",
            "error"   => $stmt->error
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
