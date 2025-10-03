<?php
// get_plan_by_id.php - Get subscription plan by ID (GET, JWT required)
require_once '../cors.php';

// ✅ Authenticate JWT (any valid user can access plans)
$decoded = authenticateJWT(); // returns array with user_id, role etc.
$userRole = isset($decoded['role']) ? strtoupper($decoded['role']) : null;

// ✅ Get plan ID from URL (query string or path)
$planId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($planId <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid plan ID provided"
    ]);
    exit();
}

try {
    // Build query with role-based visibility
    $query = "
        SELECT 
            id, 
            title, 
            type, 
            price, 
            duration_days, 
            features_json,
            admin_action
        FROM plans
        WHERE id = ?
          AND (
                (admin_action = 'approved')
                OR (admin_action = 'pending' AND ? = 'ADMIN')
              )
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $planId, $userRole);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $plan = $result->fetch_assoc();

            echo json_encode([
                "status" => true,
                "message" => "Subscription plan retrieved successfully",
                "data" => $plan
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Subscription plan not found or access denied"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve subscription plan",
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
