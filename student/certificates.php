<?php
// certificates.php - Get student certificates
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Only GET method allowed"]);
    exit();
}

// Optional filters: student_id, course_id
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$course_id  = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;

$query = "SELECT c.id, c.student_id, c.course_id, c.file_url, c.issue_date,
                 cr.title AS course_title
          FROM certificates c
          LEFT JOIN courses cr ON c.course_id = cr.id
          WHERE 1=1";

$params = [];
$types  = "";

// Add filters dynamically
if ($student_id) {
    $query .= " AND c.student_id = ?";
    $params[] = $student_id;
    $types .= "i";
}

if ($course_id) {
    $query .= " AND c.course_id = ?";
    $params[] = $course_id;
    $types .= "i";
}

$stmt = $conn->prepare($query);

// Bind params if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$certificates = [];
while ($row = $result->fetch_assoc()) {
    $certificates[] = $row;
}

http_response_code(200);
echo json_encode([
    "status" => true,
    "count" => count($certificates),
    "data" => $certificates,
    "timestamp" => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
?>
