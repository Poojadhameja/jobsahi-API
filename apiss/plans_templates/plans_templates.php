<?php
// plans_templates.php - Create new plan template (Admin, Recruiter access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT (only Admin & Recruiter allowed to create)
$decoded = authenticateJWT(['admin', 'recruiter']);
$role = $decoded['role']; // assume JWT has role

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$plan_name     = isset($data['plan_name']) ? $data['plan_name'] : '';
$type          = isset($data['type']) ? $data['type'] : '';
$price         = isset($data['price']) ? $data['price'] : 0;
$duration_days = isset($data['duration_days']) ? $data['duration_days'] : 0;
$features      = isset($data['features']) ? $data['features'] : '';
$admin_action  = isset($data['admin_action']) ? $data['admin_action'] : 'pending'; // default pending

try {
    // ✅ Insert new plan template
    $stmt = $conn->prepare("INSERT INTO plan_templates (plan_name, type, price, duration_days, features, admin_action) 
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdiss", $plan_name, $type, $price, $duration_days, $features, $admin_action);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;

        // ✅ Fetch the created record with visibility rules
        if ($role === 'admin') {
            // Admin sees everything
            $sql = "SELECT id, plan_name, type, price, duration_days, features, admin_action 
                    FROM plan_templates 
                    WHERE id = ?";
        } else {
            // Other roles → only if approved
            $sql = "SELECT id, plan_name, type, price, duration_days, features, admin_action 
                    FROM plan_templates 
                    WHERE id = ? AND admin_action = 'approval'";
        }

        $checkStmt = $conn->prepare($sql);
        $checkStmt->bind_param("i", $new_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $plan = $result->fetch_assoc();

        echo json_encode([
            "status" => true,
            "message" => "Plan template created successfully",
            "created_by" => $role,
            "plan" => $plan
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to create plan template",
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
