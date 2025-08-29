<?php
// student/payments.php - Get student course payments history
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config.php';

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => false, "message" => "Only GET method is allowed"]);
    exit;
}

// Require student_id parameter
if (!isset($_GET['student_id'])) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "student_id is required"]);
    exit;
}

$student_id = intval($_GET['student_id']);

// Query to fetch student payment history
$sql = "SELECT 
            cp.id,
            cp.student_id,
            cp.course_id,
            c.title AS course_title,
            cp.enrollment_id,
            cp.amount,
            cp.currency,
            cp.status,
            cp.method,
            cp.transaction_ref,
            cp.gateway_response_json,
            cp.paid_at,
            cp.created_at,
            cp.modified_at
        FROM course_payments cp
        LEFT JOIN courses c ON cp.course_id = c.id
        WHERE cp.student_id = ? 
          AND cp.deleted_at IS NULL
        ORDER BY cp.paid_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $payments = [];

    while ($row = $result->fetch_assoc()) {
        // Optional: Decode gateway JSON if not null
        if (!empty($row['gateway_response_json'])) {
            $row['gateway_response_json'] = json_decode($row['gateway_response_json'], true);
        }
        $payments[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Payments history fetched successfully",
        "count" => count($payments),
        "data" => $payments,
        "timestamp" => date("Y-m-d H:i:s")
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database query failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
