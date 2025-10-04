<?php
// referrals.php


declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Only POST allowed']);
    exit;
}

require_once __DIR__ . '/../db.php'; // make sure this defines $conn = new mysqli(...)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("DB connection not found. Check db.php");
    }

    // Read JSON body
    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid JSON body']);
        exit;
    }

    $referrer_id   = isset($input['referrer_id']) ? (int)$input['referrer_id'] : 0;
    $referee_email = isset($input['referee_email']) ? trim((string)$input['referee_email']) : '';
    $job_id        = isset($input['job_id']) ? (int)$input['job_id'] : 0;

    // Validate required fields
    if ($referrer_id <= 0 || $job_id <= 0 || $referee_email === '') {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Missing required fields']);
        exit;
    }
    if (!filter_var($referee_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // ✅ Check if referrer exists in users table (fixes your foreign key error)
    $userCheck = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $userCheck->bind_param("i", $referrer_id);
    $userCheck->execute();
    $userCheck->store_result();
    if ($userCheck->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid referrer_id: user not found']);
        exit;
    }
    $userCheck->close();

    // Prevent duplicate referral for same friend + job + referrer
    $check = $conn->prepare("SELECT id FROM referrals WHERE referrer_id=? AND referee_email=? AND job_id=?");
    $check->bind_param("isi", $referrer_id, $referee_email, $job_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['status' => false, 'message' => 'Referral already exists for this friend and job']);
        exit;
    }
    $check->close();

    // Insert into referrals
    $status = "pending";
    $stmt = $conn->prepare("INSERT INTO referrals (referrer_id, referee_email, job_id, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isis", $referrer_id, $referee_email, $job_id, $status);
    $stmt->execute();

    $newId = $conn->insert_id;
    $stmt->close();

    http_response_code(201);
    echo json_encode([
        'status' => true,
        'message' => 'Referral submitted successfully',
        'data' => [
            'id' => $newId,
            'referrer_id' => $referrer_id,
            'referee_email' => $referee_email,
            'job_id' => $job_id,
            'status' => $status,
            'created_at' => date("Y-m-d H:i:s")
        ]
    ]);
    $conn->close();
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
}


?>