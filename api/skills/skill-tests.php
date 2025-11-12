<?php
// skill-tests.php - Manage Skill Tests linked to job applications
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');
$current_user = authenticateJWT(['admin','recruiter','student']);
$user_role = strtolower($current_user['role'] ?? '');
$user_id = $current_user['user_id'] ?? null;

function respond($d){ echo json_encode($d); exit; }

function getStudentId($conn, $user_id) {
    $q = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();
    return $r ? (int)$r['id'] : null;
}

function getRecruiterId($conn, $user_id) {
    $q = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();
    return $r ? (int)$r['id'] : null;
}

function getApplicationMeta($conn, $application_id, $expected_student_id = null) {
    $sql = "
        SELECT a.id, a.job_id, a.student_id, a.status, a.applied_at, a.deleted_at, a.admin_action,
               j.title AS job_title, j.recruiter_id
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.id = ?
          AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
          AND LOWER(a.admin_action) = 'approved'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;

    if ($expected_student_id !== null) {
        $sql = str_replace("LIMIT 1", "AND a.student_id = ? LIMIT 1", $sql);
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param("ii", $application_id, $expected_student_id);
    } else {
        $stmt->bind_param("i", $application_id);
    }

    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) return null;
    if (strtolower($res['admin_action']) !== 'approved') return null;

    return [
        'id' => (int)$res['id'],
        'job_id' => (int)$res['job_id'],
        'student_id' => (int)$res['student_id'],
        'job_title' => $res['job_title'],
        'recruiter_id' => (int)$res['recruiter_id']
    ];
}

function countJobQuestions($conn, $job_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM skill_questions WHERE job_id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['total'] ?? 0);
}

function resolveTestJobId($conn, $test_row) {
    if (!empty($test_row['job_id'])) {
        return (int)$test_row['job_id'];
    }

    if (!empty($test_row['application_id'])) {
        $app = getApplicationMeta($conn, (int)$test_row['application_id']);
        if ($app) return (int)$app['job_id'];
    }

    if (!empty($test_row['test_name'])) {
        $stmt = $conn->prepare("SELECT id FROM jobs WHERE title = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $test_row['test_name']);
            $stmt->execute();
            $job = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($job) return (int)$job['id'];
        }
    }

    return 0;
}

/* ============================================================
   POST: Create Skill Test for an application (student only)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'student') {
        respond(["status" => false, "message" => "Only students can start a skill test"]);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) respond(["status" => false, "message" => "Invalid JSON body"]);

    $application_id = isset($data['application_id']) ? (int)$data['application_id'] : 0;
    if ($application_id <= 0) {
        respond(["status" => false, "message" => "application_id is required"]);
    }

    $student_id = getStudentId($conn, $user_id);
    if (!$student_id) respond(["status" => false, "message" => "No student profile found"]);

    $application = getApplicationMeta($conn, $application_id, $student_id);
    if (!$application) {
        respond(["status" => false, "message" => "Application not found or not approved"]);
    }

    $job_id = (int)$application['job_id'];
    $job_title = $application['job_title'];

    $question_count = countJobQuestions($conn, $job_id);
    if ($question_count === 0) {
        respond(["status" => false, "message" => "No skill test configured for this job yet"]);
    }

    $existing = $conn->prepare("
        SELECT id, score, max_score, passed, completed_at
        FROM skill_tests
        WHERE application_id = ?
          AND student_id = ?
          AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        LIMIT 1
    ");
    if (!$existing) respond(["status" => false, "message" => "Prepare failed: " . $conn->error]);

    $existing->bind_param("ii", $application_id, $student_id);
    $existing->execute();
    $existingRow = $existing->get_result()->fetch_assoc();
    $existing->close();

    if ($existingRow) {
        respond([
            "status" => true,
            "message" => "Skill test already created for this application",
            "test_id" => (int)$existingRow['id'],
            "already_exists" => true,
            "score" => (int)$existingRow['score'],
            "max_score" => (int)$existingRow['max_score'],
            "passed" => (bool)$existingRow['passed'],
            "completed_at" => $existingRow['completed_at'],
            "question_count" => $question_count
        ]);
    }

    $stmt = $conn->prepare("
        INSERT INTO skill_tests
            (student_id, application_id, job_id, test_platform, test_name, score, max_score, badge_awarded, passed, created_at)
        VALUES
            (?, ?, ?, 'JobSahi', ?, 0, 100, 0, 0, NOW())
    ");

    if (!$stmt) respond(["status" => false, "message" => "Prepare failed: " . $conn->error]);

    $stmt->bind_param("iiis", $student_id, $application_id, $job_id, $job_title);

    if ($stmt->execute()) {
        respond([
            "status" => true,
            "message" => "Skill test created successfully",
            "test_id" => $stmt->insert_id,
            "question_count" => $question_count
        ]);
    } else {
        respond(["status" => false, "message" => "Insert failed: " . $stmt->error]);
    }
}

/* ============================================================
   PUT: Finalize Skill Test (recalculate score)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
    $id = (int)$data['id'];

    $check = $conn->prepare("
        SELECT st.id, st.student_id, st.job_id, st.application_id, st.test_name
        FROM skill_tests st
        WHERE st.id = ?
        LIMIT 1
    ");
    if (!$check) respond(["status" => false, "message" => "Prepare failed: " . $conn->error]);

    $check->bind_param("i", $id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$exists) respond(["status"=>false,"message"=>"This id does not exist"]);

    $student_id = (int)$exists['student_id'];
    if ($user_role === 'student') {
        $current_student_id = getStudentId($conn, $user_id);
        if ($current_student_id !== $student_id) {
            respond(["status" => false, "message" => "You cannot finalize this skill test"]);
        }
    }

    $job_id = resolveTestJobId($conn, $exists);
    if ($job_id <= 0) respond(["status"=>false,"message"=>"Unable to resolve job for this test"]);

    $totalQ = $conn->prepare("SELECT COUNT(*) AS total FROM skill_questions WHERE job_id=?");
    $totalQ->bind_param("i", $job_id);
    $totalQ->execute();
    $totalRow = $totalQ->get_result()->fetch_assoc();
    $totalQ->close();
    $total_questions = (int)($totalRow['total'] ?? 0);

    if ($total_questions <= 0) {
        respond(["status"=>false,"message"=>"No questions found for this job"]);
    }

    $correctQ = $conn->prepare("
        SELECT COUNT(*) AS correct 
        FROM skill_attempts 
        WHERE test_id=? AND student_id=? AND is_correct=1
    ");
    $correctQ->bind_param("ii", $id, $student_id);
    $correctQ->execute();
    $correctRow = $correctQ->get_result()->fetch_assoc();
    $correctQ->close();
    $correct_answers = (int)($correctRow['correct'] ?? 0);

    $score  = (int)round(($correct_answers / $total_questions) * 100);
    $passed = ($score >= 50) ? 1 : 0;

    $update = $conn->prepare("
        UPDATE skill_tests 
        SET score = ?, max_score = 100, passed = ?, completed_at = NOW(), modified_at = NOW()
        WHERE id = ?
    ");
    $update->bind_param("iii", $score, $passed, $id);

    if ($update->execute()) {
        respond([
            "status" => true,
            "message" => "Skill test finalized successfully",
            "total_questions" => $total_questions,
            "correct_answers" => $correct_answers,
            "score" => $score,
            "max_score" => 100,
            "passed" => $passed
        ]);
    } else {
        respond(["status" => false, "message" => "Failed to update test"]);
    }
}

/* ============================================================
   GET: Fetch Skill Tests / Details
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $student_context_id = null;
    if ($user_role === 'student') {
        $student_context_id = getStudentId($conn, $user_id);
    } elseif (isset($_GET['student_id'])) {
        $student_context_id = (int)$_GET['student_id'];
    }

    if ($id) {
        $stmt = $conn->prepare("
            SELECT st.*, j.title AS job_title, j.recruiter_id
            FROM skill_tests st
            LEFT JOIN jobs j ON st.job_id = j.id
            WHERE st.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $test = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$test) respond(["status"=>false,"message"=>"No test found"]);

        if ($user_role === 'student' && (int)$test['student_id'] !== $student_context_id) {
            respond(["status" => false, "message" => "You cannot access this skill test"]);
        }

        if ($user_role === 'recruiter') {
            $recruiter_id = getRecruiterId($conn, $user_id);
            if ($recruiter_id && !empty($test['recruiter_id']) && (int)$test['recruiter_id'] !== $recruiter_id) {
                respond(["status" => false, "message" => "You cannot access this skill test"]);
            }
        }

        $job_id = resolveTestJobId($conn, $test);
        $total_questions = countJobQuestions($conn, $job_id);

        $answeredStmt = $conn->prepare("SELECT COUNT(*) AS answered FROM skill_attempts WHERE test_id = ?");
        $answeredStmt->bind_param("i", $id);
        $answeredStmt->execute();
        $answeredRow = $answeredStmt->get_result()->fetch_assoc();
        $answeredStmt->close();
        $answered_questions = (int)($answeredRow['answered'] ?? 0);

        $questions = [];
        if ($job_id) {
            $qStmt = $conn->prepare("
                SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
                       sa.selected_option, sa.is_correct
                FROM skill_questions q
                LEFT JOIN skill_attempts sa
                ON sa.question_id = q.id AND sa.test_id = ? AND sa.student_id = ?
                WHERE q.job_id = ?
                ORDER BY q.id ASC
            ");
            $target_student = $student_context_id ?? (int)$test['student_id'];
            $qStmt->bind_param("iii", $id, $target_student, $job_id);
            $qStmt->execute();
            $res = $qStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $questions[] = $row;
            }
            $qStmt->close();
        }

        $test['max_score'] = (int)($test['max_score'] ?? 100);
        $test['score'] = (int)($test['score'] ?? 0);
        $test['passed'] = (bool)$test['passed'];
        $test['application_id'] = isset($test['application_id']) ? (int)$test['application_id'] : null;
        $test['job_id'] = $job_id;

        respond([
            "status"=>true,
            "message"=>"Test details fetched successfully",
            "test"=>$test,
            "questions"=>$questions,
            "summary"=>[
                "total_questions" => $total_questions,
                "answered_questions" => $answered_questions
            ]
        ]);
    }

    if (!$student_context_id) {
        respond(["status" => false, "message" => "student_id is required"]);
    }

    $stmt = $conn->prepare("
        SELECT st.*, j.title AS job_title
        FROM skill_tests st
        LEFT JOIN jobs j ON st.job_id = j.id
        WHERE st.student_id = ?
        ORDER BY st.created_at DESC
    ");
    $stmt->bind_param("i", $student_context_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $r['max_score'] = (int)($r['max_score'] ?? 100);
        $r['score'] = (int)($r['score'] ?? 0);
        $r['passed'] = (bool)$r['passed'];
        $r['application_id'] = isset($r['application_id']) ? (int)$r['application_id'] : null;
        $r['job_id'] = resolveTestJobId($conn, $r);
        $rows[] = $r;
    }
    $stmt->close();

    respond(["status"=>true,"data"=>$rows]);
}

/* ============================================================
   Default Response
   ============================================================ */
respond(["status"=>false,"message"=>"Only GET, POST, PUT allowed"]);
?>
