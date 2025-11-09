<?php
// skill-attempts.php - Manage question attempts for skill tests
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// Roles:
// - student: create attempts, view own attempts
// - admin/recruiter: view attempts (for reporting), edit if needed
$current_user = authenticateJWT(['student', 'admin', 'recruiter']);
$user_role = $current_user['role'] ?? '';
$user_id   = $current_user['user_id'] ?? null;

function respond($d){ echo json_encode($d); exit; }

function getStudentId($conn, $user_id){
    $q = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();
    return $r ? (int)$r['id'] : null;
}

/* =========================================================
   POST: Record attempt (Student only, single attempt rule)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($user_role !== 'student') {
        respond(["status" => false, "message" => "Only students can submit attempts"]);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) respond(["status" => false, "message" => "Invalid JSON input"]);

    $required = ['test_id', 'question_id', 'selected_option', 'attempt_number', 'time_taken_seconds'];
    foreach ($required as $f) {
        if (!isset($data[$f]) || $data[$f] === '') {
            respond(["status" => false, "message" => "Missing field: $f"]);
        }
    }

    $test_id      = (int)$data['test_id'];
    $question_id  = (int)$data['question_id'];
    $selected     = strtoupper(trim($data['selected_option']));
    $attempt_no   = (int)$data['attempt_number'];
    $time_taken   = (int)$data['time_taken_seconds'];

    if (!in_array($selected, ['A','B','C','D'])) {
        respond(["status" => false, "message" => "Invalid selected_option. Allowed: A, B, C, D"]);
    }

    // ✅ Get student_id from token
    $student_id = getStudentId($conn, $user_id);
    if (!$student_id) respond(["status" => false, "message" => "Student profile not found"]);

    // ✅ Validate test belongs to this student
    $checkTest = $conn->prepare("SELECT id FROM skill_tests WHERE id = ? AND student_id = ? LIMIT 1");
    $checkTest->bind_param("ii", $test_id, $student_id);
    $checkTest->execute();
    $tRow = $checkTest->get_result()->fetch_assoc();
    $checkTest->close();
    if (!$tRow) respond(["status" => false, "message" => "Invalid test_id for this student"]);

    // ✅ Validate question & fetch correct_option
    $checkQ = $conn->prepare("SELECT correct_option FROM skill_questions WHERE id = ? LIMIT 1");
    $checkQ->bind_param("i", $question_id);
    $checkQ->execute();
    $qRow = $checkQ->get_result()->fetch_assoc();
    $checkQ->close();
    if (!$qRow) respond(["status" => false, "message" => "Invalid question_id"]);

    $correct_option = strtoupper(trim($qRow['correct_option']));
    if (!in_array($correct_option, ['A','B','C','D'])) {
        respond(["status" => false, "message" => "Invalid correct_option in DB for this question"]);
    }

    // ✅ Calculate correctness
    $is_correct = ($selected === $correct_option) ? 1 : 0;

    // ✅ Check if attempt already exists (SINGLE ATTEMPT RULE)
    $check = $conn->prepare("
        SELECT id 
        FROM skill_attempts 
        WHERE student_id = ? AND test_id = ? AND question_id = ?
        LIMIT 1
    ");
    $check->bind_param("iii", $student_id, $test_id, $question_id);
    $check->execute();
    $exist = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exist) {
        // ❌ Do NOT update. Reject multiple attempts.
        respond([
            "status" => false,
            "message" => "You have already attempted this question. Only one attempt is allowed per question."
        ]);
    }

    // ✅ Insert new attempt
    $stmt = $conn->prepare("
        INSERT INTO skill_attempts
        (student_id, test_id, question_id, selected_option, is_correct, attempt_number, time_taken_seconds, answered_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) respond(["status" => false, "message" => "Prepare failed: " . $conn->error]);

    $stmt->bind_param("iiisiii",
        $student_id,
        $test_id,
        $question_id,
        $selected,
        $is_correct,
        $attempt_no,
        $time_taken
    );

    if ($stmt->execute()) {
        respond([
            "status" => true,
            "message" => "Attempt recorded successfully",
            "attempt_id" => $stmt->insert_id,
            "is_correct" => $is_correct
        ]);
    } else {
        respond(["status" => false, "message" => "Insert failed: " . $stmt->error]);
    }
}

/* =========================================================
   GET: Retrieve attempts (same as before)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $id          = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $student_q   = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    $test_q      = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;

    if ($user_role === 'student') $student_q = getStudentId($conn, $user_id);

    if ($id) {
        $sql = "
            SELECT a.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d
            FROM skill_attempts a
            JOIN skill_questions q ON a.question_id = q.id
            WHERE a.id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
    } else {
        $where = [];
        $types = '';
        $vals  = [];
        if ($student_q) { $where[] = "a.student_id = ?"; $types .= 'i'; $vals[] = $student_q; }
        if ($test_q)    { $where[] = "a.test_id = ?";    $types .= 'i'; $vals[] = $test_q; }

        $sql = "
            SELECT a.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d
            FROM skill_attempts a
            JOIN skill_questions q ON a.question_id = q.id
        ";
        if (count($where)) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY a.id ASC";

        $stmt = $conn->prepare($sql);
        if (count($vals)) $stmt->bind_param($types, ...$vals);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    respond(["status" => true, "count" => count($rows), "data" => $rows]);
}

/* =========================================================
   PUT: Manual corrections (Admin/Recruiter)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!in_array($user_role, ['admin','recruiter'])) {
        respond(["status" => false, "message" => "Only admin or recruiter can edit attempts"]);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) respond(["status" => false, "message" => "Missing id"]);
    $id = (int)$data['id'];

    $chk = $conn->prepare("SELECT id FROM skill_attempts WHERE id = ? LIMIT 1");
    $chk->bind_param("i", $id);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$exists) respond(["status" => false, "message" => "This id is not given or does not exist"]);

    $fields = [];
    $types = '';
    $vals  = [];
    $allowed = ['selected_option','is_correct','attempt_number','time_taken_seconds'];
    foreach ($allowed as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $types   .= in_array($f, ['is_correct','attempt_number','time_taken_seconds']) ? 'i' : 's';
            $vals[]   = $data[$f];
        }
    }

    if (!count($fields)) respond(["status" => false, "message" => "No fields to update"]);

    $sql = "UPDATE skill_attempts SET " . implode(", ", $fields) . ", answered_at = NOW() WHERE id = ?";
    $types .= 'i'; $vals[] = $id;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);

    if ($stmt->execute()) respond(["status" => true, "message" => "Attempt updated"]);
    else respond(["status" => false, "message" => $stmt->error]);
}

/* =========================================================
   Default
   ========================================================= */
respond(["status" => false, "message" => "Only GET, POST, PUT allowed"]);
?>
