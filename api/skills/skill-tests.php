<?php
// skill-tests.php
require_once '../cors.php';

$current_user = authenticateJWT(['admin', 'recruiter', 'student']);
$user_role = $current_user['role'] ?? '';
$user_id = $current_user['user_id'] ?? null;

header('Content-Type: application/json');

function respond($data) {
    echo json_encode($data);
    exit;
}

/* ============================
   CREATE (POST)
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    // Only required basic info at creation
    $required = ['test_platform', 'test_name'];
    foreach ($required as $f) {
        if (!isset($data[$f])) respond(["status" => false, "message" => "Missing field: $f"]);
    }

    // Fetch student_id from student_profiles if role is student
    $student_id = null;
    if ($user_role === 'student') {
        $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result) {
            $student_id = (int)$result['id'];
        } else {
            respond(["status" => false, "message" => "No student profile found for the user"]);
        }
    } elseif (in_array($user_role, ['admin', 'recruiter'])) {
        // For admin or recruiter, allow student_id from input if provided
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : null;
        if ($student_id) {
            $stmt = $conn->prepare("SELECT student_id FROM student_profiles WHERE student_id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if (!$result) {
                respond(["status" => false, "message" => "Invalid student_id"]);
            }
        }
    } else {
        respond(["status" => false, "message" => "Unauthorized role"]);
    }

    if (!$student_id) {
        respond(["status" => false, "message" => "Student ID could not be determined"]);
    }

    $test_platform = $data['test_platform'];
    $test_name = $data['test_name'];

    $score = null;
    $max_score = $data['max_score'] ?? null;
    $completed_at = null;
    $badge_awarded = 0;
    $passed = 0;
    $admin_action = 'pending';

    try {
        $stmt = $conn->prepare("
            INSERT INTO skill_tests 
            (student_id, test_platform, test_name, score, max_score, completed_at, badge_awarded, passed, admin_action, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmt) respond(["status" => false, "message" => "Prepare failed: " . $conn->error]);

        $stmt->bind_param("issiiisis", $student_id, $test_platform, $test_name, $score, $max_score, $completed_at, $badge_awarded, $passed, $admin_action);

        if ($stmt->execute()) {
            respond(["status" => true, "message" => "Skill test created successfully", "insert_id" => $stmt->insert_id]);
        } else {
            respond(["status" => false, "message" => "Failed: " . $stmt->error]);
        }
    } catch (Exception $e) {
        respond(["status" => false, "message" => $e->getMessage()]);
    }
}

/* ============================
   UPDATE (PUT)
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) respond(["status" => false, "message" => "Missing id"]);
    $id = (int)$data['id'];

    // Student can only update their own record
    if ($user_role === 'student') {
        $check = $conn->prepare("SELECT student_id FROM skill_tests WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        if (!$res || $res['student_id'] != $user_id) {
            respond(["status" => false, "message" => "Unauthorized"]);
        }
    }

    // Allow score, max_score, completed_at, badge_awarded, passed to be updated after test
    $fields = [];
    $types = '';
    $values = [];

    $allowed = ['score', 'max_score', 'completed_at', 'badge_awarded', 'passed'];
    foreach ($allowed as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            if (in_array($f, ['score', 'max_score', 'badge_awarded', 'passed'])) $types .= 'i';
            else $types .= 's';
            $values[] = $data[$f];
        }
    }

    // ONLY admin can update admin_action
    if (isset($data['admin_action']) && $user_role === 'admin') {
        $fields[] = "admin_action = ?";
        $types .= 's';
        $values[] = $data['admin_action'];
    }

    if (count($fields) === 0) respond(["status" => false, "message" => "No fields to update"]);

    $sql = "UPDATE skill_tests SET " . implode(", ", $fields) . ", modified_at = NOW() WHERE id = ?";
    $types .= 'i';
    $values[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) respond(["status" => true, "message" => "Record updated"]);
    else respond(["status" => false, "message" => $stmt->error]);
}
?>