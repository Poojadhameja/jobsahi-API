<?php
// create_reports.php - Create new report (Admin, Recruiter access)
require_once '../cors.php';

// ✅ Authenticate JWT and allow only Admin, Student, Institute & Recruiter to create reports
$decoded = authenticateJWT(['admin', 'recruiter', 'student', 'institute']); 
$user_id = $decoded['user_id']; 
$user_role = $decoded['role']; // role must be available from JWT

// ✅ Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$report_type     = isset($data['report_type']) ? $data['report_type'] : '';
$filters_applied = isset($data['filters_applied']) ? json_encode($data['filters_applied']) : '{}';
$download_url    = isset($data['download_url']) ? $data['download_url'] : '';
$admin_action    = isset($data['admin_action']) ? $data['admin_action'] : 'pending'; 
// default 'pending' until admin approves

try {
    // ✅ Insert report
    $stmt = $conn->prepare("INSERT INTO reports 
        (generated_by, report_type, filters_applied, download_url, generated_at, admin_action)
        VALUES (?, ?, ?, ?, NOW(), ?)");

    $stmt->bind_param("issss", $user_id, $report_type, $filters_applied, $download_url, $admin_action);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Report created successfully",
            "report_id" => $stmt->insert_id,
            // "visible_to" => ($admin_action === 'pending') ? "admin only" : "all roles"
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to create report",
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
