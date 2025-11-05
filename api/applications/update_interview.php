<?php
// update_interview.php — Update existing interview (Admin, Recruiter access)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT (admin + recruiter)
$decoded = authenticateJWT(['admin', 'recruiter']); 
$user_id = $decoded['user_id'];
$user_role = strtolower($decoded['role'] ?? '');

// ✅ Allow only PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["status" => false, "message" => "Only PUT requests allowed"]);
    exit();
}

// ✅ Parse JSON body
$data = json_decode(file_get_contents("php://input"), true);

$interview_id = isset($data['interview_id']) ? intval($data['interview_id']) : 0;
$scheduled_at = isset($data['scheduled_at']) ? trim($data['scheduled_at']) : '';
$mode         = isset($data['mode']) ? trim($data['mode']) : '';
$location     = isset($data['location']) ? trim($data['location']) : '';
$status       = isset($data['status']) ? trim($data['status']) : '';
$feedback     = isset($data['feedback']) ? trim($data['feedback']) : '';

if ($interview_id <= 0) {
    echo json_encode(["status" => false, "message" => "Missing or invalid interview_id"]);
    exit();
}

try {
    // ✅ Step 1: Get interview + related recruiter
    $fetch_sql = "
        SELECT i.id, i.application_id, j.recruiter_id
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN jobs j ON a.job_id = j.id
        WHERE i.id = ?
    ";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param("i", $interview_id);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Interview not found"]);
        exit();
    }

    $row = $result->fetch_assoc();
    $recruiter_id = $row['recruiter_id'];

    // ✅ Step 2: If recruiter, ensure ownership
    if ($user_role === 'recruiter') {
        $rec_stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rec_stmt->bind_param("i", $user_id);
        $rec_stmt->execute();
        $rec_result = $rec_stmt->get_result();

        if ($rec_result->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
            exit();
        }

        $rec_profile = $rec_result->fetch_assoc();
        if ($rec_profile['id'] != $recruiter_id) {
            echo json_encode(["status" => false, "message" => "You are not authorized to update this interview"]);
            exit();
        }
    }

    // ✅ Step 3: Update existing interview record
    $stmt = $conn->prepare("
        UPDATE interviews
        SET scheduled_at = ?, 
            mode = ?, 
            location = ?, 
            status = ?, 
            feedback = ?, 
            admin_action = 'approved', 
            modified_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $scheduled_at, $mode, $location, $status, $feedback, $interview_id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Interview updated successfully",
            "interview_id" => $interview_id
        ]);
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
