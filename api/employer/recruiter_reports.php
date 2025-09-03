<?php
// reports.php - Get reports with role-based access
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate JWT - allow all roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']);
$user_role = $decoded['role']; // role from JWT
$user_id = $decoded['user_id']; // user ID from JWT

try {
    if ($user_role === 'admin') {
        // Admin sees all pending + approved records
        $stmt = $conn->prepare("SELECT * FROM reports ORDER BY generated_at DESC");
    } else {
        // Other roles see only approved records
        $stmt = $conn->prepare("SELECT * FROM reports WHERE admin_action = 'approval' ORDER BY generated_at DESC");
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $reports = [];

        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }

        echo json_encode([
            "status" => true,
            "message" => "Reports retrieved successfully",
            "data" => $reports,
            "total_records" => count($reports)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to fetch reports",
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
