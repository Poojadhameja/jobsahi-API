<?php
// get_hired_jobs.php - Get list of jobs where student has been hired/selected
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate JWT (only student or admin)
$current_user = authenticateJWT(['student', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

$role = strtolower($current_user['role'] ?? '');
$user_id = $current_user['user_id'] ?? null;

// ✅ Get student profile ID
$student_profile_id = null;

if ($role === 'student') {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res) {
        echo json_encode(["status" => false, "message" => "Student profile not found"]);
        exit;
    }
    $student_profile_id = (int)$res['id'];
} else {
    // Admin can query for any student
    if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
        $student_profile_id = (int)$_GET['student_id'];
    } elseif (isset($_GET['student_user_id']) && is_numeric($_GET['student_user_id'])) {
        $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $student_user_id = (int)$_GET['student_user_id'];
            $stmt->bind_param("i", $student_user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res) {
                $student_profile_id = (int)$res['id'];
            }
        }
    }

    if (!$student_profile_id) {
        echo json_encode(["status" => false, "message" => "student_id or student_user_id is required for admin"]);
        exit;
    }
}

// ✅ Pagination
$limit = isset($_GET['limit']) ? max((int)$_GET['limit'], 1) : 50;
$offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

// ✅ Main query - Get hired jobs (status = 'selected')
$sql = "
    SELECT 
        a.id AS application_id,
        a.job_id,
        a.status,
        a.applied_at AS application_date,
        a.modified_at AS hired_date,
        a.job_selected,
        j.title AS job_title,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.experience_required,
        j.application_deadline,
        rp.company_name,
        rp.company_logo,
        rp.industry
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
    WHERE a.student_id = ?
      AND a.status = 'selected'
      AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
      AND LOWER(a.admin_action) = 'approved'
    ORDER BY a.modified_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
    exit;
}

$stmt->bind_param("iii", $student_profile_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$hired_jobs = [];
while ($row = $result->fetch_assoc()) {
    $application_id = (int)$row['application_id'];
    
    // ✅ Try to get shortlisted date from activity_logs (if available)
    // Since shortlisting is optional, this might be NULL
    $shortlisted_date = null;
    
    // Check activity_logs for status change to 'shortlisted'
    $shortlist_check_sql = "
        SELECT created_at 
        FROM activity_logs 
        WHERE reference_table = 'applications' 
          AND reference_id = ? 
          AND action LIKE '%shortlisted%'
        ORDER BY created_at DESC 
        LIMIT 1
    ";
    $shortlist_stmt = $conn->prepare($shortlist_check_sql);
    if ($shortlist_stmt) {
        $shortlist_stmt->bind_param("i", $application_id);
        $shortlist_stmt->execute();
        $shortlist_result = $shortlist_stmt->get_result();
        if ($shortlist_row = $shortlist_result->fetch_assoc()) {
            $shortlisted_date = $shortlist_row['created_at'];
        }
        $shortlist_stmt->close();
    }
    
    // Format the response
    $hired_jobs[] = [
        "application_id" => $application_id,
        "job_id" => (int)$row['job_id'],
        "job_title" => $row['job_title'],
        "location" => $row['location'],
        "job_type" => $row['job_type'],
        "salary_min" => $row['salary_min'] !== null ? (float)$row['salary_min'] : null,
        "salary_max" => $row['salary_max'] !== null ? (float)$row['salary_max'] : null,
        "experience_required" => $row['experience_required'],
        "application_deadline" => $row['application_deadline'],
        "company_name" => $row['company_name'],
        "company_logo" => $row['company_logo'],
        "industry" => $row['industry'],
        "job_selected" => (bool)$row['job_selected'],
        "application_date" => $row['application_date'],
        "shortlisted_date" => $shortlisted_date, // NULL if not shortlisted (optional step)
        "hired_date" => $row['hired_date']
    ];
}

$stmt->close();

// ✅ Get total count for pagination
$count_sql = "
    SELECT COUNT(*) AS total 
    FROM applications a
    WHERE a.student_id = ?
      AND a.status = 'selected'
      AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
      AND LOWER(a.admin_action) = 'approved'
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $student_profile_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$conn->close();

echo json_encode([
    "status" => true,
    "message" => "Hired jobs fetched successfully",
    "count" => count($hired_jobs),
    "total" => (int)$total_count,
    "data" => $hired_jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>

