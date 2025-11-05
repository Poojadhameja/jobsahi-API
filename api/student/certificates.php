<?php
// certificates.php - Get student certificates with role-based visibility
require_once '../cors.php';

// Authenticate user (admin, student allowed)
$current_user = authenticateJWT(['admin', 'student']);
$role = $current_user['role'];  // role from JWT payload


// Optional filters
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$course_id  = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;

// Base query with role-based condition
$query = "SELECT c.id, c.student_id, c.course_id, c.file_url, c.issue_date,
                 cr.title AS course_title, c.admin_action
          FROM certificates c
          LEFT JOIN courses cr ON c.course_id = cr.id
          WHERE (
              (? = 'admin')
              OR
              (? IN ('student','recruiter','institute') AND c.admin_action = 'approved')
          )";

$params = [$role, $role];
$types  = "ss";

// Extra filters
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

// Bind parameters dynamically
$stmt->bind_param($types, ...$params);

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
