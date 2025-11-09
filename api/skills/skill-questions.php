<?php
// skill-questions.php - Manage skill test questions
require_once '../cors.php';
require_once '../db.php';

$current_user = authenticateJWT(['admin','recruiter','student']);
$user_role = $current_user['role'] ?? '';
$user_id = $current_user['user_id'] ?? null;

header('Content-Type: application/json');
function respond($d){ echo json_encode($d); exit; }

// --------------------
// POST: Create Question (Admin/Recruiter)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($user_role, ['admin','recruiter'])) 
        respond(["status"=>false,"message"=>"Only recruiter or admin can add questions"]);

    $data = json_decode(file_get_contents("php://input"), true);
    $required = ['test_id','question_text','option_a','option_b','option_c','option_d','correct_option'];
    foreach ($required as $f) {
        if (!isset($data[$f]) || $data[$f] === '') respond(["status"=>false,"message"=>"Missing field: $f"]);
    }

    $test_id = (int)$data['test_id'];
    $question_text = trim($data['question_text']);
    $opt_a = trim($data['option_a']);
    $opt_b = trim($data['option_b']);
    $opt_c = trim($data['option_c']);
    $opt_d = trim($data['option_d']);
    $correct = strtoupper(trim($data['correct_option'])); // 'A'|'B'|'C'|'D'

    // ✅ Verify that provided test_id exists in skill_tests
    $check = $conn->prepare("SELECT id FROM skill_tests WHERE id = ?");
    $check->bind_param("i", $test_id);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows === 0) {
        respond(["status" => false, "message" => "Invalid test_id. No such test exists in skill_tests."]);
    }
    $check->close();

    // ✅ Insert question safely
    $stmt = $conn->prepare("
        INSERT INTO skill_questions 
        (test_id, question_text, option_a, option_b, option_c, option_d, correct_option, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) respond(["status"=>false,"message"=>"Prepare failed: ".$conn->error]);

    $stmt->bind_param("issssss", $test_id, $question_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct);

    if ($stmt->execute()) {
        respond(["status"=>true,"message"=>"Question added successfully","insert_id"=>$stmt->insert_id]);
    } else {
        respond(["status"=>false,"message"=>$stmt->error]);
    }
}

// --------------------
// GET: Fetch Questions (Admin/Recruiter/Student)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM skill_questions WHERE id = ?");
        $stmt->bind_param("i",$id);
    } elseif ($test_id) {
        $stmt = $conn->prepare("SELECT * FROM skill_questions WHERE test_id = ? ORDER BY id ASC");
        $stmt->bind_param("i",$test_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM skill_questions ORDER BY id ASC");
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        // Hide correct answers from students
        if ($user_role === 'student') {
            unset($r['correct_option']);
        }
        $out[] = $r;
    }

    respond(["status"=>true,"data"=>$out]);
}

// --------------------
// PUT: Edit Question (Admin/Recruiter)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
    $id = (int)$data['id'];

    // ✅ Allow both admin and recruiter
    if (!in_array($user_role, ['admin','recruiter'])) {
        respond(["status"=>false,"message"=>"Only admin or recruiter can edit questions"]);
    }

    $fields = [];
    $types = '';
    $values = [];
    $allowed = ['test_id','question_text','option_a','option_b','option_c','option_d','correct_option'];

    foreach ($allowed as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $types .= ($f === 'test_id') ? 'i' : 's';
            $values[] = $data[$f];
        }
    }

    if (count($fields) === 0) respond(["status"=>false,"message"=>"No fields to update"]);

    $sql = "UPDATE skill_questions SET " . implode(", ", $fields) . ", updated_at = NOW() WHERE id = ?";
    $types .= 'i';
    $values[] = $id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        respond(["status"=>true,"message"=>"Question updated successfully"]);
    } else {
        respond(["status"=>false,"message"=>$stmt->error]);
    }
}

// --------------------
// Default Response for Unsupported Methods
// --------------------
respond(["status"=>false,"message"=>"Only GET, POST, PUT allowed"]);
?>
