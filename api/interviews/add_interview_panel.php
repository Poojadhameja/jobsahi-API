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
    // 🔐 RECRUITER OWNERSHIP CHECK (MULTIPLE PATHS)
    // ================================================================
    if ($user_role === 'recruiter') {

        // Step 1: Get recruiter_profile_id
        $rp = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ?");
        $rp->bind_param("i", $user_id);
        $rp->execute();
        $rp_res = $rp->get_result();

        if ($rp_res->num_rows == 0) {
            echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
            exit();
        }

        $recruiter_profile_id = intval($rp_res->fetch_assoc()['id']);

        // Step 2: Check if interview exists and get its structure
        // First, try to get basic interview info without assuming column names
        $checkExists = $conn->prepare("SELECT id FROM interviews WHERE id = ?");
        $checkExists->bind_param("i", $interview_id);
        $checkExists->execute();
        $existsResult = $checkExists->get_result();

        if ($existsResult->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized or invalid interview ID"
            ]);
            exit();
        }

        $isAuthorized = false;

        // OPTION 1: Check if interviews table has recruiter_id column directly
        // Try this query first - if recruiter_id column exists, this will work
        try {
            $check1 = $conn->prepare("
                SELECT id 
                FROM interviews 
                WHERE id = ? AND recruiter_id = ?
                LIMIT 1
            ");
            $check1->bind_param("ii", $interview_id, $recruiter_profile_id);
            $check1->execute();
            if ($check1->get_result()->num_rows > 0) {
                $isAuthorized = true;
            }
        } catch (Exception $e) {
            // recruiter_id column doesn't exist, try next option
        }

        // OPTION 2: If interview has application_id, join through applications -> jobs
        if (!$isAuthorized) {
            try {
                $check2 = $conn->prepare("
                    SELECT i.id
                    FROM interviews i
                    INNER JOIN applications a ON i.application_id = a.id
                    INNER JOIN jobs j ON a.job_id = j.id
                    WHERE i.id = ? AND j.recruiter_id = ?
                    LIMIT 1
                ");
                $check2->bind_param("ii", $interview_id, $recruiter_profile_id);
                $check2->execute();
                if ($check2->get_result()->num_rows > 0) {
                    $isAuthorized = true;
                }
            } catch (Exception $e) {
                // application_id might be NULL or column doesn't exist
            }
        }

        // OPTION 3: If interviews table has job_id directly (unlikely but try)
        if (!$isAuthorized) {
            try {
                $check3 = $conn->prepare("
                    SELECT i.id
                    FROM interviews i
                    INNER JOIN jobs j ON i.job_id = j.id
                    WHERE i.id = ? AND j.recruiter_id = ?
                    LIMIT 1
                ");
                $check3->bind_param("ii", $interview_id, $recruiter_profile_id);
                $check3->execute();
                if ($check3->get_result()->num_rows > 0) {
                    $isAuthorized = true;
                }
            } catch (Exception $e) {
                // job_id column doesn't exist in interviews table
            }
        }

        if (!$isAuthorized) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized or invalid interview ID"
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

    // Log error for debugging (remove in production)
    error_log("Interview Panel Error: " . $e->getMessage());
    error_log("Interview ID: " . $interview_id);
    error_log("User ID: " . $user_id);
    error_log("User Role: " . $user_role);

    echo json_encode([
        "status" => false,
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

