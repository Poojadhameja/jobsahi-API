<?php
// skill-tests.php
require_once '../cors.php';

// Authenticate JWT and get user role
$current_user = authenticateJWT(['admin', 'student']);
$user_role = $current_user['role'] ?? '';

// ----------------------
// POST: Insert Skill Test
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    // Required fields
    $required = ['student_id','test_platform','test_name','score','max_score','completed_at','badge_awarded','passed','admin_action'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            echo json_encode(["status"=>false,"message"=>"Missing field: $field"]);
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
    $admin_action  = $data['admin_action']; // pending/approved

    try {
        $stmt = $conn->prepare("
            INSERT INTO skill_tests 
            (student_id, test_platform, test_name, score, max_score, completed_at, badge_awarded, passed, admin_action, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("issiiissi", $student_id, $test_platform, $test_name, $score, $max_score, $completed_at, $badge_awarded, $passed, $admin_action);

        if ($stmt->execute()) {
            echo json_encode([
                "status"=>true,
                "message"=>"Skill test submitted successfully",
                "insert_id"=>$stmt->insert_id
            ]);
        } else {
            echo json_encode(["status"=>false,"message"=>"Failed to submit skill test"]);
        }
    } catch (Exception $e) {
        echo json_encode(["status"=>false,"message"=>$e->getMessage()]);
    }
    exit;
}

// ----------------------
// GET: Fetch Skill Tests
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($user_role === 'admin') {
            // Admin sees all records
            $query = "SELECT * FROM skill_tests ORDER BY created_at DESC";
        } else {
            // Other roles see only approved tests
            $query = "SELECT * FROM skill_tests WHERE admin_action='approved' ORDER BY created_at DESC";
        }

        $result = $conn->query($query);
        $tests = [];
        while ($row = $result->fetch_assoc()) {
            $tests[] = $row;
        }

        echo json_encode(["status"=>true,"data"=>$tests]);
    } catch (Exception $e) {
        echo json_encode(["status"=>false,"message"=>$e->getMessage()]);
    }
    exit;
}

// Invalid method
echo json_encode(["status"=>false,"message"=>"Only POST and GET requests are allowed"]);
