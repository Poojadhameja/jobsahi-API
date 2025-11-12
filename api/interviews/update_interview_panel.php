<?php
// update_interview_panel.php - Update interview panel feedback
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate for multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 
$user_id = $decoded['user_id'];
$user_role = strtolower($decoded['role'] ?? '');

// ✅ Read input JSON
$data = json_decode(file_get_contents("php://input"), true);
$panel_id = intval($data['id'] ?? 0);
$feedback = trim($data['feedback'] ?? '');
$rating = isset($data['rating']) ? floatval($data['rating']) : null;
$admin_action = trim($data['admin_action'] ?? 'approved');

if ($panel_id <= 0) {
    echo json_encode(["status" => false, "message" => "Panel ID is required"]);
    exit();
}

try {
    // 🔹 Validate recruiter access
    if ($user_role === 'recruiter') {
        $check = $conn->prepare("
            SELECT ip.id 
            FROM interview_panel ip
            JOIN interviews i ON ip.interview_id = i.id
            JOIN applications a ON i.application_id = a.id
            JOIN jobs j ON a.job_id = j.id
            WHERE ip.id = ? AND j.recruiter_id = ?
        ");
        $check->bind_param("ii", $panel_id, $user_id);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Access denied or invalid record"]);
            exit();
        }
    }

    // 🔹 Update feedback
    $stmt = $conn->prepare("
        UPDATE interview_panel 
        SET feedback = ?, rating = ?, admin_action = ?, created_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sdsi", $feedback, $rating, $admin_action, $panel_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Fetch updated record
        $get = $conn->prepare("SELECT * FROM interview_panel WHERE id = ?");
        $get->bind_param("i", $panel_id);
        $get->execute();
        $data = $get->get_result()->fetch_assoc();

        echo json_encode([
            "status" => true,
            "message" => "Panel feedback updated successfully",
            "data" => $data
        ]);
    } else {
        echo json_encode(["status" => false, "message" => "No changes made or record not found"]);
    }

} catch (Throwable $e) {
    echo json_encode(["status" => false, "message" => "Server error: " . $e->getMessage()]);
}

$conn->close();
?>
