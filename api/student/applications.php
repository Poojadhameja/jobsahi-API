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

// ---- Fetch Filters ----
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$status     = isset($_GET['status']) ? trim($_GET['status']) : null;
$job_id     = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
$limit      = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset     = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

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
            j.salary_max
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE 1=1";

$params = [];
$types = "";

// ---- Role-based filter ----
if (strtolower($current_user['role']) === 'admin') {
    // Admin can see all application statuses
    $sql .= " AND LOWER(a.admin_action) IN ('pending', 'approved', 'rejected')";
} else {
    // Students see only approved applications
    $sql .= " AND LOWER(a.admin_action) = 'approved'";
}

// ---- Optional filters ----
if (!empty($student_id) && $student_id > 0) {
    $sql .= " AND a.student_id = ?";
    $params[] = $student_id;
    $types .= "i";
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
