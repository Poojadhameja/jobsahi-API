<?php
// update_interview.php - Update or reschedule interview (Admin, Recruiter access) - PUT API
require_once '../cors.php';
require_once '../db.php'; // ensure your database connection is available

// ✅ Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ✅ Allow only PUT method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode([
        "status" => false,
        "message" => "Method not allowed. Only PUT requests are accepted."
    ]);
    exit();
}

// ✅ Authenticate JWT
$decoded = authenticateJWT(['admin', 'recruiter']);
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

// ✅ Get interview ID from URL
$interview_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($interview_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid interview ID"
    ]);
    exit();
}

// ✅ Read JSON body
$data = json_decode(file_get_contents("php://input"), true);
$scheduled_at = trim($data['scheduled_at'] ?? '');
$mode = trim($data['mode'] ?? 'online');
$location = trim($data['location'] ?? '');
$status = trim($data['status'] ?? 'scheduled');
$feedback = trim($data['feedback'] ?? '');

if (empty($scheduled_at)) {
    echo json_encode([
        "status" => false,
        "message" => "Scheduled date and time are required"
    ]);
    exit();
}

try {
    // ✅ Step 1: Verify interview exists
    $check_sql = "SELECT i.id, a.job_id 
                  FROM interviews i
                  JOIN applications a ON i.application_id = a.id
                  JOIN jobs j ON a.job_id = j.id
                  WHERE i.id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $interview_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Interview not found"
        ]);
        exit();
    }

    // ✅ Step 2: Recruiter ownership check
    if ($user_role === 'recruiter') {
        // Get recruiter_profile_id from user_id
        $profile_stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $profile_result = $profile_stmt->get_result();

        if ($profile_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Recruiter profile not found"
            ]);
            exit();
        }

        $profile_row = $profile_result->fetch_assoc();
        $recruiter_profile_id = $profile_row['id'];

        // Verify this interview belongs to this recruiter
        $verify_stmt = $conn->prepare("
            SELECT j.id 
            FROM interviews i
            JOIN applications a ON i.application_id = a.id
            JOIN jobs j ON a.job_id = j.id
            WHERE i.id = ? AND j.recruiter_id = ?
        ");
        $verify_stmt->bind_param("ii", $interview_id, $recruiter_profile_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();

        if ($verify_result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Access denied. Recruiter does not own this interview."
            ]);
            exit();
        }
    }

    // ✅ Step 3: Update interview record
    $update_stmt = $conn->prepare("
        UPDATE interviews 
        SET scheduled_at = ?, 
            mode = ?, 
            location = ?, 
            status = ?, 
            feedback = ?, 
            modified_at = NOW() 
        WHERE id = ?
    ");
    $update_stmt->bind_param("sssssi", $scheduled_at, $mode, $location, $status, $feedback, $interview_id);

    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Interview updated successfully",
                "interview_id" => $interview_id
            ]);
        } else {
            echo json_encode([
                "status" => true,
                "message" => "No changes made (data is same as before)",
                "interview_id" => $interview_id
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update interview",
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
