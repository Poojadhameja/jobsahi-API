<?php
require_once '../cors.php';
require_once '../db.php';

// AUTH
$decoded = authenticateJWT(['admin','recruiter','institute','student']);
$user_id  = intval($decoded['user_id']);
$user_role = strtolower($decoded['role'] ?? '');

// INPUT
$data = json_decode(file_get_contents("php://input"), true);

$interview_id  = intval($data['interview_id'] ?? 0);
$panelist_name = trim($data['panelist_name'] ?? '');
$feedback      = trim($data['feedback'] ?? '');
$rating        = isset($data['rating']) ? floatval($data['rating']) : 0;
$created_at    = date('Y-m-d H:i:s');

// VALIDATION
if ($interview_id <= 0) {
    echo json_encode(["status" => false, "message" => "Interview ID required"]);
    exit();
}
if (empty($panelist_name) || empty($feedback)) {
    echo json_encode(["status" => false, "message" => "Panelist name and feedback required"]);
    exit();
}

try {

    // ================================================================
    // 🔐 RECRUITER OWNERSHIP CHECK
    // ================================================================
    if ($user_role === 'recruiter') {

        // Get recruiter_profile_id
        $rp = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rp->bind_param("i", $user_id);
        $rp->execute();
        $rp_res = $rp->get_result();

        if ($rp_res->num_rows == 0) {
            echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
            exit();
        }

        $recruiter_profile_id = intval($rp_res->fetch_assoc()['id']);

        // Validate Interview Ownership
        $check = $conn->prepare("
            SELECT i.id
            FROM interviews i
            INNER JOIN applications a ON i.application_id = a.id
            INNER JOIN jobs j ON a.job_id = j.id
            WHERE i.id = ? AND j.recruiter_id = ?
            LIMIT 1
        ");
        $check->bind_param("ii", $interview_id, $recruiter_profile_id);
        $check->execute();

        if ($check->get_result()->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized — You do not own this interview"
            ]);
            exit();
        }
    }

    // ================================================================
    // 🔍 CHECK IF FEEDBACK ALREADY EXISTS
    // ================================================================
    $exists = $conn->prepare("
        SELECT id 
        FROM interview_panel 
        WHERE interview_id = ? AND panelist_name = ?
    ");
    $exists->bind_param("is", $interview_id, $panelist_name);
    $exists->execute();
    $ex_res = $exists->get_result();

    // ================================================================
    // 🔄 UPDATE EXISTING PANEL FEEDBACK
    // ================================================================
    if ($ex_res->num_rows > 0) {

        $update = $conn->prepare("
            UPDATE interview_panel
            SET feedback = ?, rating = ?, admin_action = 'approved', created_at = NOW()
            WHERE interview_id = ? AND panelist_name = ?
        ");
        // TYPES = s d i s  → 4 variables
        $update->bind_param("sdis", $feedback, $rating, $interview_id, $panelist_name);
        $update->execute();

        $msg = "Panel feedback updated successfully";

    } 

    // ================================================================
    // ➕ INSERT NEW PANEL FEEDBACK
    // ================================================================
    else {

        $insert = $conn->prepare("
            INSERT INTO interview_panel
                (interview_id, panelist_name, feedback, rating, created_at, admin_action)
            VALUES (?, ?, ?, ?, ?, 'approved')
        ");

        // TYPES = i s s d s  → 5 variables
        $insert->bind_param("issds", 
            $interview_id, 
            $panelist_name, 
            $feedback, 
            $rating, 
            $created_at
        );
        $insert->execute();

        $msg = "Panel feedback added successfully";
    }

    // ================================================================
    // 📥 FETCH UPDATED PANEL LIST
    // ================================================================
    $get = $conn->prepare("
        SELECT id, interview_id, panelist_name, feedback, rating, created_at, admin_action
        FROM interview_panel
        WHERE interview_id = ?
        ORDER BY id DESC
    ");
    $get->bind_param("i", $interview_id);
    $get->execute();

    $panel = [];
    $result = $get->get_result();
    while ($row = $result->fetch_assoc()) {
        $panel[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => $msg,
        "panelists" => $panel
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
