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

function getSkillTestContext($conn, $test_id) {
    $stmt = $conn->prepare("
        SELECT st.id, st.student_id, st.job_id, st.application_id, st.deleted_at,
               st.test_name, st.max_score,
               a.id AS application_exists, a.student_id AS application_student_id,
               a.deleted_at AS application_deleted_at,
               j.id AS job_exists
        FROM skill_tests st
        LEFT JOIN applications a ON st.application_id = a.id
        LEFT JOIN jobs j ON st.job_id = j.id
        WHERE st.id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;

    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) return null;

    return [
        "student_id" => (int)$res['student_id'],
        "job_id" => $res['job_id'] ? (int)$res['job_id'] : null,
        "application_id" => $res['application_id'] ? (int)$res['application_id'] : null,
        "application_student_id" => $res['application_student_id'] ? (int)$res['application_student_id'] : null,
        "application_deleted" => !empty($res['application_deleted_at']) && $res['application_deleted_at'] !== '0000-00-00 00:00:00',
        "test_name" => $res['test_name'] ?? null
    ];
}

function resolveSkillTestJobId($conn, $context) {
    if (!empty($context['job_id'])) {
        return (int)$context['job_id'];
    }

    if (!empty($context['application_id'])) {
        $stmt = $conn->prepare("SELECT job_id FROM applications WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $context['application_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) return (int)$row['job_id'];
        }
    }

    if (!empty($context['test_name'])) {
        $stmt = $conn->prepare("SELECT id FROM jobs WHERE title = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $context['test_name']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) return (int)$row['id'];
        }
    }

    return null;
}

function recalculateSkillTestScore($conn, $test_id, $student_id, $job_id) {
    if (!$job_id) return null;

    $totalStmt = $conn->prepare("SELECT COUNT(*) AS total FROM skill_questions WHERE job_id = ?");
    if (!$totalStmt) return null;
    $totalStmt->bind_param("i", $job_id);
    $totalStmt->execute();
    $totalRow = $totalStmt->get_result()->fetch_assoc();
    $total_questions = (int)($totalRow['total'] ?? 0);
    $totalStmt->close();

    if ($total_questions <= 0) {
        $max_score = 100;
        $update = $conn->prepare("UPDATE skill_tests SET score = 0, max_score = ?, passed = 0, modified_at = NOW() WHERE id = ?");
        $update->bind_param("ii", $max_score, $test_id);
        $update->execute();
        $update->close();

        return [
            "total_questions" => 0,
            "correct_answers" => 0,
            "attempted_questions" => 0,
            "score" => 0,
            "passed" => 0,
            "completed" => false
        ];
    }

    $correctStmt = $conn->prepare("SELECT COUNT(*) AS correct FROM skill_attempts WHERE test_id = ? AND student_id = ? AND is_correct = 1");
    $correctStmt->bind_param("ii", $test_id, $student_id);
    $correctStmt->execute();
    $correctRow = $correctStmt->get_result()->fetch_assoc();
    $correct_answers = (int)($correctRow['correct'] ?? 0);
    $correctStmt->close();

    $answeredStmt = $conn->prepare("SELECT COUNT(*) AS answered FROM skill_attempts WHERE test_id = ? AND student_id = ?");
    $answeredStmt->bind_param("ii", $test_id, $student_id);
    $answeredStmt->execute();
    $answeredRow = $answeredStmt->get_result()->fetch_assoc();
    $answered_questions = (int)($answeredRow['answered'] ?? 0);
    $answeredStmt->close();

    $score = (int)round(($correct_answers / max($total_questions, 1)) * 100);
    $passed = ($score >= 50) ? 1 : 0;
    $completed = ($answered_questions >= $total_questions && $total_questions > 0);
    $max_score = 100;

    $updateSql = $completed
        ? "UPDATE skill_tests SET score = ?, max_score = ?, passed = ?, completed_at = NOW(), modified_at = NOW() WHERE id = ?"
        : "UPDATE skill_tests SET score = ?, max_score = ?, passed = ?, modified_at = NOW() WHERE id = ?";

    $update = $conn->prepare($updateSql);
    $update->bind_param("iiii", $score, $max_score, $passed, $test_id);
    $update->execute();
    $update->close();

    return [
        "total_questions" => $total_questions,
        "correct_answers" => $correct_answers,
        "attempted_questions" => $answered_questions,
        "score" => $score,
        "passed" => $passed,
        "completed" => $completed
    ];
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

    // Extract metadata fields (optional)
    $metadata = [
        'total_time_spent_seconds' => isset($data['total_time_spent_seconds']) ? (int)$data['total_time_spent_seconds'] : null,
        'total_questions' => isset($data['total_questions']) ? (int)$data['total_questions'] : null,
        'attempted_questions' => isset($data['attempted_questions']) ? (int)$data['attempted_questions'] : null
    ];

    // Support batch submissions via attempts array.
    if (isset($data['attempts']) && is_array($data['attempts'])) {
        handleBatchAttempts($conn, $user_id, $data['attempts'], $metadata);
    }

    $required = ['test_id', 'question_id', 'selected_option', 'attempt_number', 'time_taken_seconds'];
    foreach ($required as $f) {
        if (!isset($data[$f]) || $data[$f] === '') {
            respond(["status" => false, "message" => "Missing field: $f"]);
        }
    }

    $singleAttempt = [
        'test_id' => (int)$data['test_id'],
        'question_id' => (int)$data['question_id'],
        'selected_option' => strtoupper(trim($data['selected_option'])),
        'attempt_number' => (int)$data['attempt_number'],
        'time_taken_seconds' => (int)$data['time_taken_seconds']
    ];

    handleSingleAttempt($conn, $user_id, $singleAttempt);
}

function handleSingleAttempt($conn, $user_id, $attempt)
{
    $test_id      = $attempt['test_id'];
    $question_id  = $attempt['question_id'];
    $selected     = $attempt['selected_option'];
    $attempt_no   = $attempt['attempt_number'];
    $time_taken   = $attempt['time_taken_seconds'];

    if (!in_array($selected, ['A','B','C','D'])) {
        respond(["status" => false, "message" => "Invalid selected_option. Allowed: A, B, C, D"]);
    }

    // ✅ Get student_id from token
    $student_id = getStudentId($conn, $user_id);
    if (!$student_id) respond(["status" => false, "message" => "Student profile not found"]);

    // ✅ Validate skill test context
    $testContext = getSkillTestContext($conn, $test_id);
    if (!$testContext || $testContext['student_id'] !== $student_id) {
        respond(["status" => false, "message" => "Invalid test_id for this student"]);
    }

    if ($testContext['application_deleted']) {
        respond(["status" => false, "message" => "This application is no longer active. Please re-apply to retake the skill test."]);
    }

    $job_id_for_test = resolveSkillTestJobId($conn, $testContext);

    // ✅ Validate question & fetch correct_option
    $checkQ = $conn->prepare("SELECT correct_option, job_id FROM skill_questions WHERE id = ? LIMIT 1");
    $checkQ->bind_param("i", $question_id);
    $checkQ->execute();
    $qRow = $checkQ->get_result()->fetch_assoc();
    $checkQ->close();
    if (!$qRow) respond(["status" => false, "message" => "Invalid question_id"]);

    $correct_option = strtoupper(trim($qRow['correct_option']));
    if (!in_array($correct_option, ['A','B','C','D'])) {
        respond(["status" => false, "message" => "Invalid correct_option in DB for this question"]);
    }
    $job_id_for_question = (int)$qRow['job_id'];

    if ($job_id_for_test === null) {
        $job_id_for_test = $job_id_for_question;
        $updateJob = $conn->prepare("UPDATE skill_tests SET job_id = ? WHERE id = ?");
        if ($updateJob) {
            $updateJob->bind_param("ii", $job_id_for_test, $test_id);
            $updateJob->execute();
            $updateJob->close();
        }
    } elseif ($job_id_for_test !== $job_id_for_question) {
        respond(["status" => false, "message" => "Question does not belong to this skill test"]);
    }

    if (!empty($testContext['application_id']) && $testContext['application_student_id'] !== $student_id) {
        respond(["status" => false, "message" => "You cannot attempt this skill test"]);
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
        $scoreSummary = recalculateSkillTestScore($conn, $test_id, $student_id, $job_id_for_test);
        respond([
            "status" => true,
            "message" => "Attempt recorded successfully",
            "attempt_id" => $stmt->insert_id,
            "is_correct" => $is_correct,
            "score_summary" => $scoreSummary
        ]);
    } else {
        respond(["status" => false, "message" => "Insert failed: " . $stmt->error]);
    }
}

function handleBatchAttempts($conn, $user_id, array $attempts, array $metadata = [])
{
    if (!count($attempts)) {
        respond(["status" => false, "message" => "attempts array cannot be empty"]);
    }

    $student_id = getStudentId($conn, $user_id);
    if (!$student_id) respond(["status" => false, "message" => "Student profile not found"]);

    $test_id = null;
    $normalizedAttempts = [];
    foreach ($attempts as $idx => $attempt) {
        if (!is_array($attempt)) {
            respond(["status" => false, "message" => "Each attempt must be an object"]);
        }

        $required = ['test_id', 'question_id', 'selected_option'];
        foreach ($required as $field) {
            if (!isset($attempt[$field]) || $attempt[$field] === '') {
                respond(["status" => false, "message" => "Missing field: {$field} at index {$idx}"]);
            }
        }

        $currentTestId = (int)$attempt['test_id'];
        if ($test_id === null) {
            $test_id = $currentTestId;
        } elseif ($test_id !== $currentTestId) {
            respond(["status" => false, "message" => "All attempts must belong to the same test"]);
        }

        $normalizedAttempts[] = [
            'test_id' => $currentTestId,
            'question_id' => (int)$attempt['question_id'],
            'selected_option' => strtoupper(trim($attempt['selected_option'])),
            'attempt_number' => isset($attempt['attempt_number']) ? (int)$attempt['attempt_number'] : 1,
            'time_taken_seconds' => isset($attempt['time_taken_seconds']) ? (int)$attempt['time_taken_seconds'] : 0
        ];
    }

    $testContext = getSkillTestContext($conn, $test_id);
    if (!$testContext || $testContext['student_id'] !== $student_id) {
        respond(["status" => false, "message" => "Invalid test_id for this student"]);
    }

    if ($testContext['application_deleted']) {
        respond(["status" => false, "message" => "This application is no longer active. Please re-apply to retake the skill test."]);
    }

    $job_id_for_test = resolveSkillTestJobId($conn, $testContext);

    $questionCache = [];
    $seenQuestionIds = [];

    foreach ($normalizedAttempts as $attempt) {
        if (!in_array($attempt['selected_option'], ['A','B','C','D'])) {
            respond(["status" => false, "message" => "Invalid selected_option for question {$attempt['question_id']}. Allowed: A, B, C, D"]);
        }

        if (isset($seenQuestionIds[$attempt['question_id']])) {
            respond(["status" => false, "message" => "Duplicate question_id {$attempt['question_id']} in payload"]);
        }
        $seenQuestionIds[$attempt['question_id']] = true;

        if (!isset($questionCache[$attempt['question_id']])) {
            $checkQ = $conn->prepare("SELECT correct_option, job_id FROM skill_questions WHERE id = ? LIMIT 1");
            $checkQ->bind_param("i", $attempt['question_id']);
            $checkQ->execute();
            $qRow = $checkQ->get_result()->fetch_assoc();
            $checkQ->close();
            if (!$qRow) {
                respond(["status" => false, "message" => "Invalid question_id {$attempt['question_id']}"]);
            }
            $questionCache[$attempt['question_id']] = [
                'correct_option' => strtoupper(trim($qRow['correct_option'])),
                'job_id' => (int)$qRow['job_id']
            ];
        }

        $correct_option = $questionCache[$attempt['question_id']]['correct_option'];
        if (!in_array($correct_option, ['A','B','C','D'])) {
            respond(["status" => false, "message" => "Invalid correct_option in DB for question {$attempt['question_id']}"]);
        }

        $job_id_for_question = $questionCache[$attempt['question_id']]['job_id'];

        if ($job_id_for_test === null) {
            $job_id_for_test = $job_id_for_question;
        } elseif ($job_id_for_test !== $job_id_for_question) {
            respond(["status" => false, "message" => "Question {$attempt['question_id']} does not belong to this skill test"]);
        }
    }

    if ($job_id_for_test === null) {
        respond(["status" => false, "message" => "Unable to resolve job for this skill test"]);
    }

    // Update test with resolved job_id if not already set.
    if (empty($testContext['job_id'])) {
        $updateJob = $conn->prepare("UPDATE skill_tests SET job_id = ? WHERE id = ?");
        if ($updateJob) {
            $updateJob->bind_param("ii", $job_id_for_test, $test_id);
            $updateJob->execute();
            $updateJob->close();
        }
    }

    if (!empty($testContext['application_id']) && $testContext['application_student_id'] !== $student_id) {
        respond(["status" => false, "message" => "You cannot attempt this skill test"]);
    }

    // Check for existing attempts to enforce single attempt rule.
    $questionIds = array_column($normalizedAttempts, 'question_id');
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

    $types = str_repeat('i', count($questionIds) + 2);
    $params = array_merge([$student_id, $test_id], $questionIds);

    $query = "
        SELECT question_id 
        FROM skill_attempts 
        WHERE student_id = ? 
          AND test_id = ? 
          AND question_id IN ($placeholders)
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $alreadyAttempted = [];
    while ($row = $res->fetch_assoc()) {
        $alreadyAttempted[] = (int)$row['question_id'];
    }
    $stmt->close();

    if (count($alreadyAttempted)) {
        respond([
            "status" => false,
            "message" => "You have already attempted question(s): " . implode(', ', $alreadyAttempted)
        ]);
    }

    $insert = $conn->prepare("
        INSERT INTO skill_attempts
        (student_id, test_id, question_id, selected_option, is_correct, attempt_number, time_taken_seconds, answered_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$insert) respond(["status" => false, "message" => "Prepare failed: " . $conn->error]);

    $conn->begin_transaction();
    $inserted = [];

    try {
        foreach ($normalizedAttempts as $attempt) {
            $questionMeta = $questionCache[$attempt['question_id']];
            $is_correct = ($attempt['selected_option'] === $questionMeta['correct_option']) ? 1 : 0;

            $insert->bind_param(
                "iiisiii",
                $student_id,
                $test_id,
                $attempt['question_id'],
                $attempt['selected_option'],
                $is_correct,
                $attempt['attempt_number'],
                $attempt['time_taken_seconds']
            );

            if (!$insert->execute()) {
                throw new Exception("Insert failed for question {$attempt['question_id']}: " . $insert->error);
            }

            $inserted[] = [
                'question_id' => $attempt['question_id'],
                'is_correct' => $is_correct,
                'attempt_id' => $insert->insert_id
            ];
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        $insert->close();
        respond(["status" => false, "message" => $e->getMessage()]);
    }

    $insert->close();

    // Update skill_tests table with metadata if provided
    if (!empty($metadata['total_time_spent_seconds']) || !empty($metadata['total_questions']) || !empty($metadata['attempted_questions'])) {
        $updateFields = [];
        $updateTypes = '';
        $updateValues = [];

        if (isset($metadata['total_time_spent_seconds']) && $metadata['total_time_spent_seconds'] >= 0) {
            $updateFields[] = "total_time_spent_seconds = ?";
            $updateTypes .= 'i';
            $updateValues[] = $metadata['total_time_spent_seconds'];
        }

        if (isset($metadata['total_questions']) && $metadata['total_questions'] > 0) {
            $updateFields[] = "total_questions = ?";
            $updateTypes .= 'i';
            $updateValues[] = $metadata['total_questions'];
        }

        if (isset($metadata['attempted_questions']) && $metadata['attempted_questions'] >= 0) {
            $updateFields[] = "attempted_questions = ?";
            $updateTypes .= 'i';
            $updateValues[] = $metadata['attempted_questions'];
        }

        // Validation: attempted_questions <= total_questions
        if (isset($metadata['attempted_questions']) && isset($metadata['total_questions'])) {
            if ($metadata['attempted_questions'] > $metadata['total_questions']) {
                respond([
                    "status" => false,
                    "message" => "attempted_questions cannot be greater than total_questions"
                ]);
            }
        }

        if (count($updateFields) > 0) {
            $updateTypes .= 'i';
            $updateValues[] = $test_id;
            $updateSql = "UPDATE skill_tests SET " . implode(", ", $updateFields) . ", modified_at = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param($updateTypes, ...$updateValues);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    }

    $scoreSummary = recalculateSkillTestScore($conn, $test_id, $student_id, $job_id_for_test);

    respond([
        "status" => true,
        "message" => "Attempts recorded successfully",
        "attempts_inserted" => count($inserted),
        "attempt_results" => $inserted,
        "score_summary" => $scoreSummary
    ]);
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
