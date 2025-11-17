<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT (allow both admin & student)
$current_user = authenticateJWT(['admin', 'student']);

// ✅ Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

// ✅ Get role and user_id
$role = strtolower($current_user['role'] ?? '');
$user_id = $current_user['user_id'] ?? null;

// ✅ For students: Automatically get their student_profile_id from JWT
$current_student_profile_id = null;
if ($role === 'student' && $user_id) {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            $current_student_profile_id = (int)$res['id'];
        }
    }
}

// ---- Fetch Filters ----
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$status     = isset($_GET['status']) ? trim($_GET['status']) : null;
$job_id     = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
$limit      = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset     = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// ✅ Security: Students can only see their own applications
if ($role === 'student') {
    if ($current_student_profile_id) {
        // Force student_id to be the logged-in student's ID (ignore any user input)
        $student_id = $current_student_profile_id;
    } else {
        echo json_encode(["status" => false, "message" => "Student profile not found"]);
        exit;
    }
}

// ---- Build SQL ----
$sql = "SELECT 
            a.id AS application_id,
            a.student_id,
            a.job_id,
            a.status,
            a.applied_at,
            a.admin_action AS application_admin_action,
            j.title AS job_title,
            j.location,
            j.job_type,
            j.salary_min,
            j.salary_max,
            rp.company_name
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE 1=1";

// ✅ Always filter only approved admin_action and non-deleted
$sql .= " AND LOWER(a.admin_action) = 'approved'";
$sql .= " AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')";

$params = [];
$types = "";

// ---- Student ID filter (REQUIRED for security) ----
if (!empty($student_id) && $student_id > 0) {
    $sql .= " AND a.student_id = ?";
    $params[] = $student_id;
    $types .= "i";
} else {
    // ✅ If student_id is not provided and role is not admin, return empty (security)
    if ($role !== 'admin') {
        echo json_encode([
            "status" => false, 
            "message" => "student_id is required",
            "count" => 0,
            "data" => []
        ]);
        exit;
    }
}

if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($job_id) && $job_id > 0) {
    $sql .= " AND a.job_id = ?";
    $params[] = $job_id;
    $types .= "i";
}

// ---- Pagination ----
$sql .= " ORDER BY a.applied_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// ---- Execute ----
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare error: " . mysqli_error($conn)]);
    exit;
}
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ---- Collect ----
$applications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $applications[] = $row;
}

// ---- Response ----
echo json_encode([
    "status" => true,
    "message" => "Applications fetched successfully",
    "count" => count($applications),
    "data" => $applications,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
