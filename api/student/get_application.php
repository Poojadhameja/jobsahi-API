<?php
// get_application.php - Fetch single application (Student/Recruiter/Admin access)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate (admin, recruiter, student can access)
$current_user = authenticateJWT(['admin', 'recruiter', 'student']); 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["message" => "Application ID is required and must be numeric", "status" => false]);
    exit;
}

$applicationId = intval($_GET['id']);
$role = strtolower($current_user['role']);
$user_id = intval($current_user['user_id'] ?? 0);

// ✅ Resolve profile IDs
$student_profile_id = null;
$recruiter_profile_id = null;

if ($role === 'student') {
    $q = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    if ($r->num_rows > 0) {
        $student_profile_id = $r->fetch_assoc()['id'];
    }
    $q->close();
}

if ($role === 'recruiter') {
    $q = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    if ($r->num_rows > 0) {
        $recruiter_profile_id = $r->fetch_assoc()['id'];
    }
    $q->close();
}

// ✅ Base query — include admin_action = 'approved' by default for all roles
$sql = "
    SELECT 
        a.id AS application_id,
        a.student_id,
        a.job_id,
        a.status,
        a.applied_at,
        a.cover_letter,
        a.created_at,
        a.modified_at,
        j.title AS job_title,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.recruiter_id
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.id = ?
      AND LOWER(a.admin_action) = 'approved'
";

// ✅ Role-based restrictions
$bindTypes = "i";
$bindValues = [$applicationId];

if ($role === 'student' && $student_profile_id) {
    $sql .= " AND a.student_id = ?";
    $bindTypes .= "i";
    $bindValues[] = $student_profile_id;
} elseif ($role === 'recruiter' && $recruiter_profile_id) {
    $sql .= " AND j.recruiter_id = ?";
    $bindTypes .= "i";
    $bindValues[] = $recruiter_profile_id;
} elseif (!in_array($role, ['admin', 'recruiter', 'student'])) {
    echo json_encode(["message" => "Unauthorized access", "status" => false]);
    exit;
}

// ✅ Execute query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . $conn->error, "status" => false]);
    exit;
}

$stmt->bind_param($bindTypes, ...$bindValues);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $job_id_for_skill = (int)$row['job_id'];
    $student_profile_for_skill = (int)$row['student_id'];

    // Count total skill questions configured for this job
    $questionCount = 0;
    try {
        $questionCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM skill_questions WHERE job_id = ?");
        if ($questionCountStmt) {
            $questionCountStmt->bind_param("i", $job_id_for_skill);
            $questionCountStmt->execute();
            $questionRes = $questionCountStmt->get_result()->fetch_assoc();
            $questionCountStmt->close();
            $questionCount = (int)($questionRes['total'] ?? 0);
        }
    } catch (mysqli_sql_exception $e) {
        $questionCount = 0;
    }

    // Fetch existing skill test (if any) linked to this application
    $skillTestStmt = $conn->prepare("
        SELECT id, score, max_score, passed, completed_at, created_at, modified_at,
               total_time_spent_seconds, total_questions, attempted_questions
        FROM skill_tests
        WHERE application_id = ?
          AND student_id = ?
          AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ORDER BY created_at DESC
        LIMIT 1
    ");

    $skillTestRow = null;
    $attemptCount = 0;
    if ($skillTestStmt) {
        $skillTestStmt->bind_param("ii", $applicationId, $student_profile_for_skill);
        $skillTestStmt->execute();
        $skillTestRow = $skillTestStmt->get_result()->fetch_assoc();
        $skillTestStmt->close();
    }

    if ($skillTestRow) {
        $attemptStmt = $conn->prepare("SELECT COUNT(*) AS total FROM skill_attempts WHERE test_id = ? AND student_id = ?");
        if ($attemptStmt) {
            $attemptStmt->bind_param("ii", $skillTestRow['id'], $student_profile_for_skill);
            $attemptStmt->execute();
            $attemptRes = $attemptStmt->get_result()->fetch_assoc();
            $attemptStmt->close();
            $attemptCount = (int)($attemptRes['total'] ?? 0);
        }
    }

    $skillTestOverview = [
        "available" => $questionCount > 0,
        "test_created" => (bool)$skillTestRow,
        "test_id" => $skillTestRow ? (int)$skillTestRow['id'] : null,
        "status" => $skillTestRow
            ? (!empty($skillTestRow['completed_at']) ? 'completed' : 'in_progress')
            : 'not_started',
        "score" => $skillTestRow ? (int)($skillTestRow['score'] ?? 0) : null,
        "max_score" => $skillTestRow ? (int)($skillTestRow['max_score'] ?? 100) : null,
        "passed" => $skillTestRow ? (bool)$skillTestRow['passed'] : null,
        "completed_at" => $skillTestRow['completed_at'] ?? null,
        "answered_questions" => $attemptCount,
        "total_questions" => $questionCount,
        "time_spent_seconds" => $skillTestRow && !empty($skillTestRow['completed_at']) && isset($skillTestRow['total_time_spent_seconds'])
            ? (int)$skillTestRow['total_time_spent_seconds']
            : null,
        "can_start_now" => $questionCount > 0 && (!$skillTestRow || empty($skillTestRow['completed_at']))
    ];

    $row['skill_test_overview'] = $skillTestOverview;

    echo json_encode([
        "message" => "Application fetched successfully",
        "status" => true,
        "data" => $row,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(["message" => "Application not found or not accessible", "status" => false]);
}

$stmt->close();
$conn->close();
?>
