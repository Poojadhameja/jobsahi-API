<?php
// add_interview_panel.php - Add interview panel member/feedback (Admin, Recruiter access)
require_once '../cors.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 
$user_id = $decoded['user_id']; 
$user_role = $decoded['role'];

// Get interview ID from URL parameter
$interview_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($interview_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid interview ID"
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$panelist_name = isset($data['panelist_name']) ? trim($data['panelist_name']) : '';
$feedback = isset($data['feedback']) ? trim($data['feedback']) : '';
$rating = isset($data['rating']) && $data['rating'] !== '' ? floatval($data['rating']) : null;

// Validate required fields
if (empty($panelist_name)) {
    echo json_encode([
        "status" => false,
        "message" => "Panelist name is required"
    ]);
    exit();
}

try {
    // Check if interview exists and belongs to recruiter (if recruiter role)
    if ($user_role === 'recruiter') {
        $check_stmt = $conn->prepare("
            SELECT i.id FROM interviews i 
            JOIN applications a ON i.application_id = a.id
            JOIN jobs j ON a.job_id = j.id 
            WHERE i.id = ? AND j.recruiter_id = ?
        ");
        $check_stmt->bind_param("ii", $interview_id, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Interview not found or access denied"
            ]);
            exit();
        }
    }

    // Check if panelist already exists for this interview
    $exists_stmt = $conn->prepare("
        SELECT id FROM interview_panel WHERE interview_id = ? AND panelist_name = ?
    ");
    $exists_stmt->bind_param("is", $interview_id, $panelist_name);
    $exists_stmt->execute();
    $exists_result = $exists_stmt->get_result();

    if ($exists_result->num_rows > 0) {
        // Update existing panelist record
        $update_stmt = $conn->prepare("
            UPDATE interview_panel 
            SET feedback = ?, rating = ?, created_at = NOW() 
            WHERE interview_id = ? AND panelist_name = ?
        ");
        // Use "sdis" to match 4 parameters
        $update_stmt->bind_param("sdis", $feedback, $rating, $interview_id, $panelist_name);

        if ($update_stmt->execute()) {
            $message = "Interview panelist feedback updated successfully";
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to update panelist feedback",
                "error" => $update_stmt->error
            ]);
            exit();
        }
    } else {
        // Insert new panelist record
        $stmt = $conn->prepare("
            INSERT INTO interview_panel (interview_id, panelist_name, feedback, rating, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("issd", $interview_id, $panelist_name, $feedback, $rating);

        if ($stmt->execute()) {
            $message = "Interview panelist added successfully";
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to add panelist",
                "error" => $stmt->error
            ]);
            exit();
        }
    }

    // ✅ Fetch interview panel with admin_action visibility
    $panel_stmt = $conn->prepare("
        SELECT ip.*, i.admin_action
        FROM interview_panel ip
        JOIN interviews i ON ip.interview_id = i.id
        WHERE ip.interview_id = ? AND (
            i.admin_action = 'approved' OR (? = 'admin' AND i.admin_action = 'pending')
        )
    ");
    $panel_stmt->bind_param("is", $interview_id, $user_role);
    $panel_stmt->execute();
    $panel_result = $panel_stmt->get_result();

    $panels = [];
    while ($row = $panel_result->fetch_assoc()) {
        $panels[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => $message,
        "panelists" => $panels
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
