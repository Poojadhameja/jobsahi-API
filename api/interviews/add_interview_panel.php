<?php
// add_interview_panel.php - Add or update interview panel feedback (Admin, Recruiter, Institute, Student access)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT for multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 
$user_id = $decoded['user_id'];
$user_role = strtolower($decoded['role'] ?? '');

// ✅ Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

// ✅ Extract required fields
$interview_id   = intval($data['interview_id'] ?? 0);
$panelist_name  = trim($data['panelist_name'] ?? '');
$feedback       = trim($data['feedback'] ?? '');
$rating         = isset($data['rating']) ? floatval($data['rating']) : 0;
$notes          = trim($data['notes'] ?? '');
$admin_action   = "approved"; // default value
$created_at     = date('Y-m-d H:i:s');

// ✅ Validate required inputs
if ($interview_id <= 0) {
    echo json_encode(["status" => false, "message" => "Interview ID is required"]);
    exit();
}

if (empty($panelist_name) || empty($feedback)) {
    echo json_encode(["status" => false, "message" => "Panelist name and feedback are required"]);
    exit();
}

try {
    // 🔹 If recruiter, ensure they own this interview
   // 🔹 If recruiter, ensure they own this interview
if ($user_role === 'recruiter') {

    // Step 1: Get recruiter_profile_id from logged-in user_id
    $rp = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
    $rp->bind_param("i", $user_id);
    $rp->execute();
    $rp_res = $rp->get_result();
    
    if ($rp_res->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
        exit();
    }

    $recruiter_profile_id = intval($rp_res->fetch_assoc()['id']);

    // Step 2: Now verify interview ownership using recruiter_profile_id
    $check = $conn->prepare("
        SELECT i.id 
        FROM interviews i
        JOIN applications a ON i.application_id = a.id
        JOIN jobs j ON a.job_id = j.id
        WHERE i.id = ? AND j.recruiter_id = ?
    ");
    $check->bind_param("ii", $interview_id, $recruiter_profile_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "You are not authorized to add panel feedback for this interview"
        ]);
        exit();
    }
}


    // 🔹 Check if feedback already exists for this panelist
    $exists = $conn->prepare("SELECT id FROM interview_panel WHERE interview_id = ? AND panelist_name = ?");
    $exists->bind_param("is", $interview_id, $panelist_name);
    $exists->execute();
    $exists_res = $exists->get_result();

    if ($exists_res->num_rows > 0) {
        // 🔸 Update feedback
        $update = $conn->prepare("
            UPDATE interview_panel 
            SET feedback = ?, rating = ?, admin_action = ?, created_at = NOW()
            WHERE interview_id = ? AND panelist_name = ?
        ");
        $update->bind_param("sdsss", $feedback, $rating, $admin_action, $interview_id, $panelist_name);
        $update->execute();
        $message = "Interview panelist feedback updated successfully";
    } else {
        // 🔸 Insert new record
        $insert = $conn->prepare("
            INSERT INTO interview_panel (interview_id, panelist_name, feedback, rating, created_at, admin_action)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param("issdss", $interview_id, $panelist_name, $feedback, $rating, $created_at, $admin_action);
        $insert->execute();
        $message = "Interview panelist added successfully";
    }

    // 🔹 Fetch updated feedback list
    $fetch = $conn->prepare("
        SELECT id, interview_id, panelist_name, feedback, rating, created_at, admin_action
        FROM interview_panel
        WHERE interview_id = ?
        ORDER BY created_at DESC
    ");
    $fetch->bind_param("i", $interview_id);
    $fetch->execute();
    $result = $fetch->get_result();

    $panelists = [];
    while ($row = $result->fetch_assoc()) {
        $panelists[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => $message,
        "panelists" => $panelists
    ]);

} catch (Throwable $e) {
    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
