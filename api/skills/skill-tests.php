<?php
// skill-tests 
<<<<<<< HEAD
=======

>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

<<<<<<< HEAD
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate and check for student role
authenticateJWT('student');

=======
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
include "../db.php"; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST requests allowed"]);
    exit;
}

// Get raw input JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$required = ['student_id', 'test_platform', 'test_name', 'score', 'max_score', 'completed_at', 'badge_awarded', 'passed'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        echo json_encode(["status" => false, "message" => "Missing field: $field"]);
        exit;
    }
}

// Extract fields
$student_id    = $data['student_id'];
$test_platform = $data['test_platform'];
$test_name     = $data['test_name'];
$score         = $data['score'];
$max_score     = $data['max_score'];
$completed_at  = $data['completed_at'];
$badge_awarded = $data['badge_awarded'];
$passed        = $data['passed'];

try {
    // Insert into skill_tests table
    $stmt = $conn->prepare("
        INSERT INTO skill_tests 
            (student_id, test_platform, test_name, score, max_score, completed_at, badge_awarded, passed, created_at, modified_at) 
        VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
<<<<<<< HEAD
    $stmt->bind_param("issiiisi", $student_id, $test_platform, $test_name, $score, $max_score, $completed_at, $badge_awarded, $passed);
    
=======

    $stmt->bind_param("issiiisi", $student_id, $test_platform, $test_name, $score, $max_score, $completed_at, $badge_awarded, $passed);

>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Skill test result submitted successfully",
            "insert_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode(["status" => false, "message" => "Failed to submit result"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
<<<<<<< HEAD
?>
=======
?>
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
