<?php
// update_employer_status.php - Update employer verification status (Admin access only)
require_once '../cors.php';

// Authenticate JWT and allow admin role only
$decoded = authenticateJWT(['admin']);

try {
    // Get user_id from URL parameter
    $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($user_id <= 0) {
        throw new Exception("Invalid user ID provided");
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['admin_action'])) {
        throw new Exception("admin_action is required");
    }

    $admin_action = trim($input['admin_action']);
    
    // Validate allowed admin_action values
    $allowed_actions = ['approved', 'rejected', 'pending']; // customize as needed
    if (!in_array($admin_action, $allowed_actions)) {
        throw new Exception("Invalid admin_action. Allowed values: " . implode(', ', $allowed_actions));
    }

    // Check if user exists and is a recruiter
    $checkStmt = $conn->prepare("SELECT id, user_name, email, role, is_verified FROM users WHERE id = ? AND role = 'recruiter'");
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        throw new Exception("Employer (recruiter) not found");
    }

    $currentUser = $checkResult->fetch_assoc();
    $checkStmt->close();

    // Map admin_action to is_verified
    // approved → is_verified = 1, pending/rejected → is_verified = 0
    $is_verified = ($admin_action === 'approved') ? 1 : 0;

    // Update users.is_verified
    $updateStmt = $conn->prepare("UPDATE users SET is_verified = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("ii", $is_verified, $user_id);

    if ($updateStmt->execute()) {
        // Fetch updated user data
        $selectStmt = $conn->prepare("SELECT id as user_id, user_name, email, role, is_verified FROM users WHERE id = ?");
        $selectStmt->bind_param("i", $user_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $updatedUser = $result->fetch_assoc();
        $selectStmt->close();

        // Derive admin_action from is_verified for response
        $derived_admin_action = ($updatedUser['is_verified'] == 1) ? 'approved' : 'pending';

        echo json_encode([
            "status" => true,
            "message" => "Employer verification status updated successfully",
            "data" => [
                "user_id" => $updatedUser['user_id'],
                "user_name" => $updatedUser['user_name'],
                "email" => $updatedUser['email'],
                "is_verified" => intval($updatedUser['is_verified']),
                "admin_action" => $derived_admin_action
            ]
        ]);
    } else {
        throw new Exception("Failed to update employer verification status: " . $updateStmt->error);
    }

    $updateStmt->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>

