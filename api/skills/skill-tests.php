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
    $student_id    = (int)$data['student_id'];
    $test_platform = $data['test_platform'];
    $test_name     = $data['test_name'];
    $score         = (int)$data['score'];
    $max_score     = (int)$data['max_score'];
    $completed_at  = $data['completed_at']; // Expected format: "2025-09-30 18:10:42"
    $badge_awarded = (int)$data['badge_awarded']; // 0 or 1
    $passed        = (int)$data['passed']; // 0 or 1
    $admin_action  = $data['admin_action']; // pending/approved

    // Validate completed_at format (YYYY-MM-DD HH:MM:SS)
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $completed_at)) {
        echo json_encode(["status"=>false,"message"=>"Invalid completed_at format. Expected: YYYY-MM-DD HH:MM:SS"]);
        exit;
    }

    // Validate admin_action values
    if (!in_array($admin_action, ['pending', 'approved'])) {
        echo json_encode(["status"=>false,"message"=>"Invalid admin_action. Must be 'pending' or 'approved'"]);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO skill_tests 
            (student_id, test_platform, test_name, score, max_score, completed_at, badge_awarded, passed, admin_action, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        // Corrected binding: i=integer, s=string, i=integer for badge_awarded and passed
        $stmt->bind_param("issiisiis", 
            $student_id,      // i - integer
            $test_platform,   // s - string
            $test_name,       // s - string
            $score,           // i - integer
            $max_score,       // i - integer
            $completed_at,    // s - string (datetime)
            $badge_awarded,   // i - integer (0 or 1)
            $passed,          // i - integer (0 or 1)
            $admin_action     // s - string
        );

        if ($stmt->execute()) {
            echo json_encode([
                "status"=>true,
                "message"=>"Skill test submitted successfully",
                "insert_id"=>$stmt->insert_id
            ]);
        } else {
            echo json_encode(["status"=>false,"message"=>"Failed to submit skill test: " . $stmt->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(["status"=>false,"message"=>"Error: " . $e->getMessage()]);
    }
    exit;
}

// Invalid method
echo json_encode(["status"=>false,"message"=>"Only POST and GET requests are allowed"]);
?>