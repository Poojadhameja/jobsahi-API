<?php
// jobs.php - Job Listings API (New logic using job_flags table)
require_once '../cors.php';
require_once '../db.php';

// Authenticate all roles
$decoded = authenticateJWT(['student', 'admin', 'recruiter', 'institute']);
$userRole = $decoded['role'] ?? null;

if (!$userRole) {
    echo json_encode(["message" => "Unauthorized: Role not found", "status" => false]);
    exit;
}

// ------------------------------------------------------------------
//  STUDENT PROFILE DETECTION
// ------------------------------------------------------------------
$student_profile_id = null;
if ($userRole === 'student') {

    $user_id = $decoded['id'] ?? $decoded['user_id'] ?? $decoded['student_id'] ?? null;

    if ($user_id) {
        $st = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ?");
        $st->bind_param("i", $user_id);
        $st->execute();
        $res = $st->get_result();
        if ($res->num_rows > 0) {
            $student_profile_id = $res->fetch_assoc()['id'];
        }
        $st->close();
    }
}

// ------------------------------------------------------------------
//  RECRUITER PROFILE FIX (ONLY THIS WAS ADDED) ⭐
// ------------------------------------------------------------------
$recruiter_profile_id = null;

if ($userRole === 'recruiter') {

    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    if ($user_id > 0) {
        $st2 = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
        $st2->bind_param("i", $user_id);
        $st2->execute();
        $res2 = $st2->get_result();

        if ($res2->num_rows > 0) {
            $recruiter_profile_id = intval($res2->fetch_assoc()['id']);
        }

        $st2->close();
    }
}

// ------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET allowed", "status" => false]);
    exit;
}

// Single Job Mode
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id > 0) {
    $sql = "
        SELECT 
            j.id,
            j.recruiter_id,
            j.title,
            j.description,
            j.location,
            j.skills_required,
            j.salary_min,
            j.salary_max,
            j.job_type,
            j.experience_required,
            j.application_deadline,
            j.is_remote,
            j.no_of_vacancies,
            j.status,
            j.is_featured,
            j.created_at,
            rp.company_name,
            ci.person_name,
            ci.phone,
            ci.additional_contact,
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS views,

            (
                SELECT 
                    CASE
                        WHEN jf.job_id IS NULL THEN 'approved'
                        WHEN jf.admin_action = 'approved' THEN 'approved'
                        ELSE 'flagged'
                    END
                FROM job_flags jf
                WHERE jf.job_id = j.id
                LIMIT 1
            ) AS admin_status

        FROM jobs j
        LEFT JOIN recruiter_profiles rp ON rp.id = j.recruiter_id
        LEFT JOIN recruiter_company_info ci ON ci.job_id = j.id
        WHERE j.id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Job not found"]);
        exit;
    }

    $job = $result->fetch_assoc();

    // Check saved/applied for student
    if ($userRole === 'student' && $student_profile_id) {

        // Saved
        $q1 = $conn->prepare("SELECT 1 FROM saved_jobs WHERE student_id = ? AND job_id = ?");
        $q1->bind_param("ii", $student_profile_id, $job_id);
        $q1->execute();
        $job['is_saved'] = $q1->get_result()->num_rows > 0 ? 1 : 0;
        $q1->close();

        // Applied
        $q2 = $conn->prepare("
            SELECT 1 FROM applications 
            WHERE student_id = ? AND job_id = ? 
              AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $q2->bind_param("ii", $student_profile_id, $job_id);
        $q2->execute();
        $job['is_applied'] = $q2->get_result()->num_rows > 0 ? 1 : 0;
        $q2->close();
    }

    $stmt->close();
    echo json_encode([
        "status" => true,
        "message" => "Job details loaded",
        "data" => $job,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    exit;
}


// -------------------------------------------------------------
// DEFAULT LISTING
// -------------------------------------------------------------

$filters = [];
$params = [];
$types = "";

// Keyword filter
if (!empty($_GET['keyword'])) {
    $filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $kw = "%".$_GET['keyword']."%";
    $params[] = $kw;
    $params[] = $kw;
    $types .= "ss";
}

// Location filter
if (!empty($_GET['location'])) {
    $filters[] = "j.location = ?";
    $params[] = $_GET['location'];
    $types .= "s";
}

// Job type filter
if (!empty($_GET['job_type'])) {
    $filters[] = "j.job_type = ?";
    $params[] = $_GET['job_type'];
    $types .= "s";
}

// Status filter
if (!empty($_GET['status'])) {
    $filters[] = "j.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Remote
if (!empty($_GET['is_remote'])) {
    $filters[] = "j.is_remote = ?";
    $params[] = $_GET['is_remote'];
    $types .= "i";
}

// Featured
if (isset($_GET['featured']) && $_GET['featured'] === 'true') {
    $filters[] = "j.is_featured = 1";
}


// 🔥 Main Listing Query
$sql = "
    SELECT 
        j.id,
        j.recruiter_id,
        j.title,
        j.description,
        j.location,
        j.skills_required,
        j.salary_min,
        j.salary_max,
        j.job_type,
        j.experience_required,
        j.application_deadline,
        j.is_remote,
        j.no_of_vacancies,
        j.status,
        j.is_featured,
        j.created_at,
        rp.company_name,
        (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS views,

        (
            SELECT 
                CASE
                    WHEN jf.job_id IS NULL THEN 'approved'
                    WHEN jf.admin_action = 'approved' THEN 'approved'
                    ELSE 'flagged'
                END
            FROM job_flags jf
            WHERE jf.job_id = j.id
            LIMIT 1
        ) AS admin_status
";

// Student flags
if ($userRole === 'student' && $student_profile_id) {
    $sql .= ",
        CASE WHEN EXISTS (
            SELECT 1 FROM saved_jobs sj 
            WHERE sj.student_id = $student_profile_id AND sj.job_id = j.id
        ) THEN 1 ELSE 0 END AS is_saved,

        CASE WHEN EXISTS (
            SELECT 1 FROM applications a 
            WHERE a.student_id = $student_profile_id AND a.job_id = j.id 
            AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
        ) THEN 1 ELSE 0 END AS is_applied
    ";
}

$sql .= " 
    FROM jobs j
    LEFT JOIN recruiter_profiles rp ON rp.id = j.recruiter_id
";

// -------------------------------------------------------------
// SAFE FILTER HANDLING + RECRUITER RESTRICTION ⭐
// -------------------------------------------------------------
if (!empty($filters)) {
    $sql .= " WHERE " . implode(" AND ", $filters);
} else {
    $sql .= " WHERE 1";
}

// Recruiter-only filter (main FIX)
if ($userRole === 'recruiter' && $recruiter_profile_id) {
    $sql .= " AND j.recruiter_id = $recruiter_profile_id";
}

$sql .= " ORDER BY j.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}

echo json_encode([
    "status" => true,
    "message" => "Jobs fetched successfully",
    "count" => count($jobs),
    "data" => $jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);

$stmt->close();
$conn->close();
?>
