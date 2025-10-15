<?php
// skill-questions.php
require_once '../cors.php';

$current_user = authenticateJWT(['admin','recruiter','student']);
$user_role = $current_user['role'] ?? '';
$user_id = $current_user['user_id'] ?? null;

header('Content-Type: application/json');
function respond($d){ echo json_encode($d); exit; }

// --------------------
// POST: Create Question (Admin/Recruiter)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_GET['action']))) {
    if (!in_array($user_role, ['admin','recruiter'])) 
        respond(["status"=>false,"message"=>"Only recruiter or admin can add questions"]);

    $data = json_decode(file_get_contents("php://input"), true);
    $required = ['test_id','question_text','option_a','option_b','option_c','option_d','correct_option'];
    foreach ($required as $f) if (!isset($data[$f])) respond(["status"=>false,"message"=>"Missing $f"]);

    $test_id = (int)$data['test_id'];
    $question_text = $data['question_text'];
    $opt_a = $data['option_a'];
    $opt_b = $data['option_b'];
    $opt_c = $data['option_c'];
    $opt_d = $data['option_d'];
    $correct = strtoupper($data['correct_option']); // 'A'|'B'|'C'|'D'

    $stmt = $conn->prepare("INSERT INTO skill_questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_option, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issssss", $test_id, $question_text, $opt_a, $opt_b, $opt_c, $opt_d, $correct);

    if ($stmt->execute()) respond(["status"=>true,"message"=>"Question added","insert_id"=>$stmt->insert_id]);
    else respond(["status"=>false,"message"=>$stmt->error]);
}

// --------------------
// GET: Fetch Questions
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
        if ($user_role === 'student') {
            unset($r['correct_option']); // hide correct answer for students
        }
        $out[] = $r;
    }
    respond(["status"=>true,"data"=>$out]);
}

// --------------------
// PUT: Edit Question
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
    $id = (int)$data['id'];

    // Since user_id is not in the table, remove ownership check or adjust logic
    // For now, allow admin only to edit (remove user_id check)
    if ($user_role !== 'admin') {
        respond(["status"=>false,"message"=>"Only admin can edit"]);
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

    $sql = "UPDATE skill_questions SET " . implode(", ", $fields) . " WHERE id = ?";
    $types .= 'i';
    $values[] = $id;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) respond(["status"=>true,"message"=>"Updated"]);
    else respond(["status"=>false,"message"=>$stmt->error]);
}

// --------------------
// DELETE: Delete Question
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
    $id = (int)$data['id'];

    // Since user_id is not in the table, remove ownership check or adjust logic
    // For now, allow admin only to delete (remove user_id check)
    if ($user_role !== 'admin') {
        respond(["status"=>false,"message"=>"Only admin can delete"]);
    }

    $stmt = $conn->prepare("DELETE FROM skill_questions WHERE id = ?");
    $stmt->bind_param("i",$id);
    if ($stmt->execute()) respond(["status"=>true,"message"=>"Deleted"]);
    else respond(["status"=>false,"message"=>$stmt->error]);
}

// --------------------
// POST: Student Answer (new feature)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'answer') {
    if ($user_role !== 'student') respond(["status"=>false,"message"=>"Only students can submit answers"]);

    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['question_id']) || !isset($data['selected_option'])) {
        respond(["status"=>false,"message"=>"Missing question_id or selected_option"]);
    }

    $question_id = (int)$data['question_id'];
    $selected = strtoupper(trim($data['selected_option'])); // 'A'|'B'|'C'|'D'

    // Fetch correct option
    $stmt = $conn->prepare("SELECT correct_option FROM skill_questions WHERE id = ?");
    $stmt->bind_param("i",$question_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) respond(["status"=>false,"message"=>"Question not found"]);
    $row = $res->fetch_assoc();

    $correct = $row['correct_option'];
    $is_correct = ($selected === $correct) ? 1 : 0;

    // Save answer (optional: create student_answers table)
    $stmt = $conn->prepare("INSERT INTO student_answers (user_id, question_id, selected_option, is_correct, answered_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisi", $user_id, $question_id, $selected, $is_correct);
    $stmt->execute();

    respond([
        "status" => true,
        "your_answer" => $selected,
        "correct_option" => $correct, // reveal now
        "is_correct" => (bool)$is_correct
    ]);
}

respond(["status"=>false,"message"=>"Only GET, POST, PUT, DELETE allowed"]);
?>