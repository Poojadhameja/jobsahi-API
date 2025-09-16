<?php
// get_plans_templates.php - Get plans templates (Role-based access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Method not allowed. Only GET requests are supported."
    ]);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

try {
    // ✅ Authenticate JWT and allow multiple roles
    $decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 
    $role = strtolower($decoded['role']); // assuming 'role' is returned from token

    // ✅ Build SQL query based on role
    if ($role === 'admin') {
        // Admin sees both pending & approval
        $sql = "SELECT * FROM plan_templates WHERE admin_action IN ('pending', 'approval')";
    } else {
        // Other roles see only approval
        $sql = "SELECT * FROM plan_templates WHERE admin_action = 'approval'";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $plans_templates = [];
    while ($row = $result->fetch_assoc()) {
        $plans_templates[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Plans templates retrieved successfully",
        "data" => $plans_templates,
        "count" => count($plans_templates)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
