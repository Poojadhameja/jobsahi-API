<?php
// applications.php - Fetch all applications with optional filters

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include JWT helper & middleware
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow multiple roles
$current_user = authenticateJWT(['admin', 'student']);

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ---- Fetch Filters from Query Params ----
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$status     = isset($_GET['status']) ? trim($_GET['status']) : null; // e.g., pending, accepted, rejected
$job_id     = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
$limit      = isset($_GET['limit']) ? intval($_GET['limit']) : 50; // default 50
$offset     = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// ---- Build Dynamic SQL ----
$sql = "SELECT a.id AS application_id, a.student_id, a.job_id, a.status, a.applied_at,
               j.title AS job_title, j.location, j.job_type, j.salary_min, j.salary_max
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE 1=1";

$params = [];
$types = "";

// Optional filters
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

$sql .= " ORDER BY a.applied_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// ---- Prepare & Execute ----
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// Bind dynamically
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ---- Collect Data ----
$applications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $applications[] = $row;
}

// ---- Response ----
echo json_encode([
    "message" => "Applications fetched successfully",
    "status" => true,
    "count" => count($applications),
    "data" => $applications,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
