<?php
// skill-tests.php - Manage Skill Tests for a specific Job (calculate marks based on skill_attempts.is_correct)
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');
$current_user = authenticateJWT(['admin','recruiter','student']);
$user_role = $current_user['role'] ?? '';
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

/* ============================================================
   POST: Create Skill Test (Only one per job per student)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'student')
        respond(["status"=>false,"message"=>"Only student can start test"]);

    $data = json_decode(file_get_contents("php://input"), true);
    $job_id = (int)($data['job_id'] ?? 0);
    $question_id = isset($data['question_id']) ? (int)$data['question_id'] : null;

    if (!$job_id) respond(["status"=>false,"message"=>"job_id is required"]);

    $student_id = getStudentId($conn, $user_id);
    if (!$student_id) respond(["status"=>false,"message"=>"No student profile found"]);

    // ✅ Validate job exists
    $checkJob = $conn->prepare("SELECT title FROM jobs WHERE id=? LIMIT 1");
    $checkJob->bind_param("i",$job_id);
    $checkJob->execute();
    $job = $checkJob->get_result()->fetch_assoc();
    $checkJob->close();
    if(!$job) respond(["status"=>false,"message"=>"Invalid job_id"]);

    $job_title = $job['title'];

    // ✅ Check if test already exists for this job & student
    $exists = $conn->prepare("
        SELECT id FROM skill_tests 
        WHERE student_id=? AND test_name=? LIMIT 1
    ");
    $exists->bind_param("is", $student_id, $job_title);
    $exists->execute();
    $test = $exists->get_result()->fetch_assoc();
    $exists->close();

    if ($test) {
        // 🟡 Return existing test instead of creating another one
        respond([
            "status"=>true,
            "message"=>"Test already exists for this job",
            "test_id"=>$test['id']
        ]);
    }

    // ✅ Create new skill test
    $stmt=$conn->prepare("
        INSERT INTO skill_tests(student_id, question_id, test_platform, test_name, score, max_score, badge_awarded, passed, created_at)
        VALUES (?, ?, 'JobSahi', ?, 0, 100, 0, 0, NOW())
    ");
    if(!$stmt) respond(["status"=>false,"message"=>"Prepare failed: ".$conn->error]);

    $stmt->bind_param("iis",$student_id,$question_id,$job_title);

    if($stmt->execute()){
        respond([
            "status"=>true,
            "message"=>"New Skill test created successfully",
            "test_id"=>$stmt->insert_id
        ]);
    } else {
        respond(["status"=>false,"message"=>$stmt->error]);
    }
}

/* ============================================================
   PUT: Finalize Skill Test (calculate based on skill_attempts.is_correct)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) respond(["status"=>false,"message"=>"Missing id"]);
    $id = (int)$data['id'];

    // ✅ Check if test exists
    $check = $conn->prepare("SELECT id, student_id, test_name FROM skill_tests WHERE id=? LIMIT 1");
    $check->bind_param("i",$id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if(!$exists) respond(["status"=>false,"message"=>"This id is not given or does not exist"]);

    $student_id = (int)$exists['student_id'];
    $test_name = $exists['test_name'];

    // ✅ Find job_id using test_name (job title)
    $jobStmt = $conn->prepare("SELECT id FROM jobs WHERE title=? LIMIT 1");
    $jobStmt->bind_param("s",$test_name);
    $jobStmt->execute();
    $job = $jobStmt->get_result()->fetch_assoc();
    $jobStmt->close();
    $job_id = $job ? (int)$job['id'] : 0;

    // ✅ Total questions for this job
    $qTotal = $conn->prepare("SELECT COUNT(*) AS total FROM skill_questions WHERE job_id=?");
    $qTotal->bind_param("i",$job_id);
    $qTotal->execute();
    $qRes = $qTotal->get_result()->fetch_assoc();
    $total_questions = (int)($qRes['total'] ?? 0);
    $qTotal->close();

    if($total_questions === 0)
        respond(["status"=>false,"message"=>"No questions found for this job"]);

    // ✅ Ensure each question only has one attempt
    $dupCheck = $conn->prepare("
        SELECT question_id, COUNT(*) AS cnt 
        FROM skill_attempts 
        WHERE test_id=? AND student_id=?
        GROUP BY question_id
        HAVING cnt > 1
    ");
    $dupCheck->bind_param("ii",$id,$student_id);
    $dupCheck->execute();
    $dups = $dupCheck->get_result();
    $dupCheck->close();
    if ($dups->num_rows > 0) {
        respond(["status"=>false,"message"=>"Duplicate attempts found. Each question can only be attempted once."]);
    }

    // ✅ Total correct answers from skill_attempts
    $correctQuery = $conn->prepare("
        SELECT COUNT(*) AS correct
        FROM skill_attempts
        WHERE test_id=? AND student_id=? AND is_correct=1
    ");
    $correctQuery->bind_param("ii",$id,$student_id);
    $correctQuery->execute();
    $correctData = $correctQuery->get_result()->fetch_assoc();
    $correctQuery->close();
    $correct_answers = (int)($correctData['correct'] ?? 0);

    // ✅ Calculate score based on correct answers
    $score = round(($correct_answers / $total_questions) * 100, 2);
    $passed = ($score >= 50) ? 1 : 0;

    // ✅ Update skill_tests table
    $update = $conn->prepare("
        UPDATE skill_tests
        SET score=?, passed=?, completed_at=NOW(), modified_at=NOW()
        WHERE id=?
    ");
    $update->bind_param("iii",$score,$passed,$id);

    if($update->execute()){
        respond([
            "status"=>true,
            "message"=>"Skill test finalized successfully",
            "total_questions"=>$total_questions,
            "correct_answers"=>$correct_answers,
            "score"=>$score,
            "max_score"=>100,
            "passed"=>$passed
        ]);
    } else {
        respond(["status"=>false,"message"=>$update->error]);
    }
}

/* ============================================================
   GET: Fetch Test + All Questions + Attempts (added max_score)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $student_id = isset($_GET['student_id'])
        ? (int)$_GET['student_id']
        : getStudentId($conn, $user_id);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id) {
        // ✅ Fetch test details
        $stmt = $conn->prepare("SELECT * FROM skill_tests WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        $test = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if(!$test) respond(["status"=>false,"message"=>"No test found"]);

        // ✅ Force max_score = 100
        $test['max_score'] = 100;

        // ✅ Get job_id
        $jobStmt = $conn->prepare("SELECT id FROM jobs WHERE title=? LIMIT 1");
        $jobStmt->bind_param("s",$test['test_name']);
        $jobStmt->execute();
        $job = $jobStmt->get_result()->fetch_assoc();
        $jobStmt->close();
        $job_id = $job ? (int)$job['id'] : 0;

        // ✅ Fetch all questions and student attempts
        $qStmt = $conn->prepare("
            SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
                   sa.selected_option, sa.is_correct
            FROM skill_questions q
            LEFT JOIN skill_attempts sa
            ON sa.question_id = q.id AND sa.test_id=? AND sa.student_id=?
            WHERE q.job_id=? ORDER BY q.id ASC
        ");
        $qStmt->bind_param("iii",$id,$student_id,$job_id);
        $qStmt->execute();
        $result = $qStmt->get_result();
        $questions = [];
        while($row = $result->fetch_assoc()) $questions[] = $row;
        $qStmt->close();

        respond([
            "status"=>true,
            "message"=>"Test details fetched successfully",
            "test"=>$test,
            "questions"=>$questions
        ]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM skill_tests WHERE student_id=? ORDER BY created_at DESC");
        $stmt->bind_param("i",$student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while($r=$res->fetch_assoc()) {
            $r['max_score'] = 100; // ensure also added in list view
            $rows[]=$r;
        }
        respond(["status"=>true,"data"=>$rows]);
    }
}

/* ============================================================
   Default Response
   ============================================================ */
respond(["status"=>false,"message"=>"Only GET, POST, PUT allowed"]);
?>
