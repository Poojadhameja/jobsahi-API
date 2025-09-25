<?php
// jobs.php - Job Listings API (Role-based access with admin_action filter)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate all roles
$decoded = authenticateJWT(['student', 'admin', 'recruiter', 'institute']);  // decoded JWT payload

// Ensure we got the role correctly
$userRole = isset($decoded['role']) ? $decoded['role'] : null;

if (!$userRole) {
    echo json_encode(["message" => "Unauthorized: Role not found in token", "status" => false]);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

require_once "../db.php";
if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// Collect filters from query params
$filters = [];
$params = [];
$types  = "";

// ✅ Role-based filter for admin_action
if ($userRole === 'admin') {
    // Admin sees both pending + approval
    $filters[] = "(j.admin_action = 'pending' OR j.admin_action = 'approval')";
} else {
    // Other roles only see approved jobs
    $filters[] = "j.admin_action = 'approval'";
}

// Keyword search (title/description)
if (!empty($_GET['keyword'])) {
    $filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $kw = "%" . $_GET['keyword'] . "%";
    $params[] = $kw;
    $params[] = $kw;
    $types   .= "ss";
}

// Location filter
if (!empty($_GET['location'])) {
    $filters[] = "j.location = ?";
    $params[] = $_GET['location'];
    $types   .= "s";
}

// Job type filter
if (!empty($_GET['job_type'])) {
    $filters[] = "j.job_type = ?";
    $params[] = $_GET['job_type'];
    $types   .= "s";
}

// Status filter
if (!empty($_GET['status'])) {
    $filters[] = "j.status = ?";
    $params[] = $_GET['status'];
    $types   .= "s";
}

// Remote filter
if (!empty($_GET['is_remote'])) {
    $filters[] = "j.is_remote = ?";
    $params[] = $_GET['is_remote'];
    $types   .= "i";
}

// Build query
$sql = "SELECT 
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
            j.admin_action,
            j.created_at,
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS views
        FROM jobs j";

if (!empty($filters)) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}

$sql .= " ORDER BY j.created_at DESC";

// Prepare statement
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// Bind filters
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$jobs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $jobs[] = $row;
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

// Return JSON
echo json_encode([
    "message" => "Jobs fetched successfully",
    "status" => true,
    "count" => count($jobs),
    "data" => $jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
