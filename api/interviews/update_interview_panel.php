<?php
// update_interview_panel.php - Update interview panel by panel ID
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate for multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 
$user_id = intval($decoded['user_id'] ?? 0);
$user_role = strtolower($decoded['role'] ?? '');

// ✅ Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only PUT or POST method allowed"]);
    exit();
}

// ✅ Read input JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => false, "message" => "Invalid JSON input"]);
    exit();
}

// ✅ Extract fields
$panel_id = intval($data['id'] ?? $data['panel_id'] ?? 0);
$panelist_name = isset($data['panelist_name']) ? trim($data['panelist_name']) : null;
$feedback = isset($data['feedback']) ? trim($data['feedback']) : null;
$rating = isset($data['rating']) ? intval($data['rating']) : null;
$admin_action = isset($data['admin_action']) ? trim($data['admin_action']) : null;

// ✅ Validate panel ID
if ($panel_id <= 0) {
    echo json_encode(["status" => false, "message" => "Panel ID is required"]);
    exit();
}

// ✅ Validate rating range (1-5)
if ($rating !== null && ($rating < 1 || $rating > 5)) {
    echo json_encode(["status" => false, "message" => "Rating must be between 1 and 5"]);
    exit();
}

// ✅ Validate admin_action if provided
if ($admin_action !== null && !in_array($admin_action, ['pending', 'approved', 'rejected'])) {
    echo json_encode(["status" => false, "message" => "Invalid admin_action. Must be: pending, approved, or rejected"]);
    exit();
}

try {
    // 🔹 First check if panel exists
    $check_panel = $conn->prepare("SELECT id, interview_id FROM interview_panel WHERE id = ?");
    $check_panel->bind_param("i", $panel_id);
    $check_panel->execute();
    $panel_result = $check_panel->get_result();
    $panel_data = $panel_result->fetch_assoc();
    $check_panel->close();

    if (!$panel_data) {
        echo json_encode(["status" => false, "message" => "Panel record not found"]);
        exit();
    }

    // 🔹 Validate recruiter access (if recruiter role)
    if ($user_role === 'recruiter') {
        $check = $conn->prepare("
            SELECT ip.id 
            FROM interview_panel ip
            JOIN interviews i ON ip.interview_id = i.id
            JOIN applications a ON i.application_id = a.id
            JOIN jobs j ON a.job_id = j.id
            JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
            WHERE ip.id = ? AND rp.user_id = ?
        ");
        $check->bind_param("ii", $panel_id, $user_id);
        $check->execute();
        $res = $check->get_result();
        $check->close();

        if ($res->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Access denied or invalid record"]);
            exit();
        }
    }

    // 🔹 Build dynamic UPDATE query (only update provided fields)
    $update_fields = [];
    $params = [];
    $types = "";

    if ($panelist_name !== null) {
        $update_fields[] = "panelist_name = ?";
        $params[] = $panelist_name;
        $types .= "s";
    }

    if ($feedback !== null) {
        $update_fields[] = "feedback = ?";
        $params[] = $feedback;
        $types .= "s";
    }

    if ($rating !== null) {
        $update_fields[] = "rating = ?";
        $params[] = $rating;
        $types .= "i";
    }

    if ($admin_action !== null) {
        $update_fields[] = "admin_action = ?";
        $params[] = $admin_action;
        $types .= "s";
    }

    // ✅ Check if there are fields to update
    if (empty($update_fields)) {
        echo json_encode(["status" => false, "message" => "No fields provided to update"]);
        exit();
    }

    // Add panel_id to params for WHERE clause
    $params[] = $panel_id;
    $types .= "i";

    // Build and execute UPDATE query
    $sql = "UPDATE interview_panel SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(["status" => false, "message" => "Database prepare error: " . $conn->error]);
        exit();
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    if ($stmt->affected_rows > 0 || $stmt->affected_rows === 0) {
        // Fetch updated record
        $get = $conn->prepare("
            SELECT 
                id,
                interview_id,
                panelist_name,
                feedback,
                rating,
                created_at,
                admin_action
            FROM interview_panel 
            WHERE id = ?
        ");
        $get->bind_param("i", $panel_id);
        $get->execute();
        $updated_data = $get->get_result()->fetch_assoc();
        $get->close();

        echo json_encode([
            "status" => true,
            "message" => "Panel feedback updated successfully",
            "data" => $updated_data
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(["status" => false, "message" => "Failed to update panel record"]);
    }

    $stmt->close();

} catch (Throwable $e) {
    echo json_encode(["status" => false, "message" => "Server error: " . $e->getMessage()]);
}

$conn->close();
?>
