<?php
// referrals.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Only GET allowed']);
    exit;
}

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate and allow both admin , recruiter and student roles
authenticateJWT(['admin', 'student', 'recruiter']);

require_once __DIR__ . '/../db.php'; // $conn = new mysqli(...)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("DB connection not found. Check db.php");
    }

    // Require referrer_id
    $referrer_id = isset($_GET['referrer_id']) ? (int)$_GET['referrer_id'] : 0;
    if ($referrer_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Missing or invalid referrer_id']);
        exit;
    }

    // Fetch referrals
    $sql = "SELECT id, referrer_id, referee_email, job_id, status, created_at 
            FROM referrals 
            WHERE referrer_id = ? 
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $referrer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $referrals = [];
    while ($row = $result->fetch_assoc()) {
        $referrals[] = $row;
    }
    $stmt->close();
    $conn->close();

    echo json_encode([
        'status' => true,
        'count' => count($referrals),
        'data' => $referrals
    ]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>