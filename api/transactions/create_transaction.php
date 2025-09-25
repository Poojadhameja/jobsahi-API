<?php
// create_transaction.php - Create new transaction (Admin, Institute access)
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

// Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(); // returns array
$user_role = $decoded['role'] ?? '';  // assuming 'role' exists in JWT
$user_id   = $decoded['user_id'] ?? 0;

// ---------- POST: Create Transaction (Admin / Institute only) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = json_decode(file_get_contents("php://input"), true);

    $amount      = isset($data['amount']) ? floatval($data['amount']) : 0;
    $method      = isset($data['method']) ? trim($data['method']) : '';
    $purpose     = isset($data['purpose']) ? trim($data['purpose']) : '';
    $status      = isset($data['status']) ? trim($data['status']) : 'pending';
    $admin_action = 'pending'; // New transactions default to 'pending'

    if (empty($method) || empty($purpose) || $amount <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Amount, method, and purpose are required"
        ]);
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, method, purpose, status, admin_action) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idssss", $user_id, $amount, $method, $purpose, $status, $admin_action);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Transaction created successfully",
                "transaction_id" => $stmt->insert_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to create transaction",
                "error"   => $stmt->error
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => false,
            "message" => "Error: " . $e->getMessage()
        ]);
    }

    $conn->close();
    exit();
}
?>