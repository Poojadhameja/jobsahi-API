<?php
// skill-tests.php - Manage Skill Tests (Create & Update)
require_once '../cors.php';
require_once '../db.php';

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

    // ✅ Validate required fields
    $required = ['test_platform', 'test_name'];
    foreach ($required as $f) {
        if (!isset($data[$f]) || $data[$f] === '') {
            respond(["status" => false, "message" => "Missing field: $f"]);
        }
    }

    // ✅ Determine student_id
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
        $student_id = isset($data['student_id']) ? (int)$data['student_id'] : null;
        if ($student_id) {
            $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if (!$result) {
                respond(["status" => false, "message" => "Invalid student_id"]);
            }
        } else {
            respond(["status" => false, "message" => "student_id is required for admin/recruiter"]);
        }
    } else {
        respond(["status" => false, "message" => "Unauthorized role"]);
    }

    if (!$student_id) {
        respond(["status" => false, "message" => "Student ID could not be determined"]);
    }

    // ✅ Prepare input values
    $test_platform = trim($data['test_platform']);
    $test_name = trim($data['test_name']);

    // ✅ FIX: Prevent NULL constraint violation
    $score = isset($data['score']) ? (int)$data['score'] : 0;
    $max_score = isset($data['max_score']) ? (int)$data['max_score'] : 0;
    $completed_at = $data['completed_at'] ?? null;
    $badge_awarded = isset($data['badge_awarded']) ? (int)$data['badge_awarded'] : 0;
    $passed = isset($data['passed']) ? (int)$data['passed'] : 0;
    $admin_action = 'pending';

    // ✅ CHECK if this student already has this test
    $check = $conn->prepare("
        SELECT id 
        FROM skill_tests 
        WHERE student_id = ? AND test_platform = ? AND test_name = ?
        LIMIT 1
    ");
    $check->bind_param("iss", $student_id, $test_platform, $test_name);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        // ✅ Test already exists for this student — don't insert duplicate
        respond([
            "status" => true,
            "message" => "Test already exists for this student",
            "existing_id" => $existing['id']
        ]);
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO skill_tests 
            (student_id, test_platform, test_name, score, max_score, completed_at, badge_awarded, passed, admin_action, created_at, modified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$stmt) respond(["status" => false, "message" => "Prepare failed: " . $conn->error]);

        $stmt->bind_param(
            "issiiisis",
            $student_id,
            $test_platform,
            $test_name,
            $score,
            $max_score,
            $completed_at,
            $badge_awarded,
            $passed,
            $admin_action
        );

        if ($stmt->execute()) {
            respond([
                "status" => true,
                "message" => "Skill test created successfully",
                "insert_id" => $stmt->insert_id
            ]);
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

    // ✅ Student can only update their own record
    if ($user_role === 'student') {
        $check = $conn->prepare("SELECT st.student_id, sp.user_id 
                                 FROM skill_tests st 
                                 JOIN student_profiles sp ON st.student_id = sp.id
                                 WHERE st.id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        if (!$res || $res['user_id'] != $user_id) {
            respond(["status" => false, "message" => "Unauthorized"]);
        }
    }

    // ✅ Collect updatable fields
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

    // ✅ Only admin can update admin_action
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

    if ($stmt->execute()) {
        respond(["status" => true, "message" => "Record updated"]);
    } else {
        respond(["status" => false, "message" => $stmt->error]);
    }
}
?>
