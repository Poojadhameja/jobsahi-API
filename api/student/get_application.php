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
        j.recruiter_id,
        rp.company_name,
        rp.company_logo
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
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

    // ✅ Handle company_logo URL conversion (R2 support + local files)
    $company_logo = $row['company_logo'] ?? "";
    if (!empty($company_logo)) {
        // Check if it's already an R2 URL
        if (strpos($company_logo, 'http') === 0 && 
            (strpos($company_logo, 'r2.dev') !== false || 
             strpos($company_logo, 'r2.cloudflarestorage.com') !== false)) {
            // Already R2 URL - use directly
            $row['company_logo'] = $company_logo;
        } else {
            // Local file path - convert to full URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $logo_base = '/jobsahi-API/api/uploads/recruiter_logo/';
            $clean_logo = str_replace(["\\", "/uploads/recruiter_logo/", "./", "../"], "", $company_logo);
            $local_logo_path = __DIR__ . '/../uploads/recruiter_logo/' . $clean_logo;
            if (file_exists($local_logo_path)) {
                $row['company_logo'] = $protocol . $host . $logo_base . $clean_logo;
            } else {
                // File doesn't exist locally, keep original or set to empty
                $row['company_logo'] = $company_logo;
            }
        }
    } else {
        $row['company_logo'] = "";
    }

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
