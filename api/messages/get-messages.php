<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate and allow both admin, recruiter, institute and student roles
authenticateJWT(['admin', 'student','recruiter','institute']);

include "../db.php";

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

// Optional filter: user_id
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

try {
    if ($user_id) {
        $sql = "SELECT 
                    id,
                    sender_id,
                    receiver_id,
                    message,
                    type,
                    created_at
                FROM messages
                WHERE receiver_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    } else {
        $sql = "SELECT 
                    id,
                    sender_id,
                    receiver_id,
                    message,
                    type,
                    created_at
                FROM messages";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    echo json_encode([
        "status" => true,
        "data" => $messages
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching messages data",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>