<?php
// skill-tests.php - List attempts & results for skill tests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../db.php';
<<<<<<< HEAD
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate JWT for student role
authenticateJWT('student'); // Only allow students
=======
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

// Optional filter: student_id
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

try {
    if ($student_id) {
        $sql = "SELECT 
                    id,
                    student_id,
                    test_platform,
                    test_name,
                    score,
                    max_score,
                    completed_at,
                    badge_awarded,
                    passed,
                    created_at,
                    modified_at
                FROM skill_tests
                WHERE deleted_at IS NULL AND student_id = ?
                ORDER BY completed_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
    } else {
        $sql = "SELECT 
                    id,
                    student_id,
                    test_platform,
                    test_name,
                    score,
                    max_score,
                    completed_at,
                    badge_awarded,
                    passed,
                    created_at,
                    modified_at
                FROM skill_tests
                WHERE deleted_at IS NULL
                ORDER BY completed_at DESC";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $tests = [];
    while ($row = $result->fetch_assoc()) {
        $tests[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Skill test attempts fetched successfully",
        "count" => count($tests),
        "data" => $tests,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching skill tests",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
