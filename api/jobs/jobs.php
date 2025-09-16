<?php
include '../CORS.php';
require_once "../helpers/response_helper.php";
require_once "../helpers/rate_limiter.php";

// Apply rate limiting (200 requests per hour)
RateLimiter::apply('jobs_listing', 200, 3600);

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
    ResponseHelper::error("Only GET requests allowed", 405, "METHOD_NOT_ALLOWED");
}

require_once "../db.php";
if (!$conn) {
    ResponseHelper::error("DB connection failed: " . mysqli_connect_error(), 500, "DB_CONNECTION_ERROR");
}

/**
 * Query params:
 * keyword, location, job_type, status, is_remote
 * page (default 1), limit (default 20, max 100)
 * sort (created_at|salary_min|salary_max|title|application_deadline), order (asc|desc)
 */

// Get pagination parameters
$pagination = ResponseHelper::getPaginationParams(20, 100);

// Get sorting parameters
$sorting = ResponseHelper::getSortingParams(
    ['created_at', 'salary_min', 'salary_max', 'title', 'application_deadline'],
    'created_at',
    'DESC'
);

// filters
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
    $keyword = ResponseHelper::sanitize($_GET['keyword']);
    if (strlen($keyword) >= 2) { // Minimum 2 characters for search
        $filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
        $kw = "%{$keyword}%";
        $params[] = $kw; $params[] = $kw;
        $types   .= "ss";
    }
}

// Location filter
if (!empty($_GET['location'])) {
    $location = ResponseHelper::sanitize($_GET['location']);
    $filters[] = "j.location = ?";
    $params[] = $location;
    $types   .= "s";
}

// Job type filter
if (!empty($_GET['job_type'])) {
    $job_type = ResponseHelper::sanitize($_GET['job_type']);
    $allowed_job_types = ['full_time', 'part_time', 'internship', 'contract'];
    if (in_array($job_type, $allowed_job_types)) {
        $filters[] = "j.job_type = ?";
        $params[] = $job_type;
        $types   .= "s";
    }
}

// status (enum: open, closed, paused) — default open if not provided
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status = ResponseHelper::sanitize($_GET['status']);
    $allowed_statuses = ['open', 'closed', 'paused'];
    if (in_array($status, $allowed_statuses)) {
        $filters[] = "j.status = ?";
        $params[] = $status;
        $types   .= "s";
    }
} else {
    $filters[] = "j.status = 'open'";
}

// is_remote (0/1)
if (isset($_GET['is_remote']) && $_GET['is_remote'] !== '') {
    $is_remote = (int)$_GET['is_remote'];
    if (in_array($is_remote, [0, 1])) {
        $filters[] = "j.is_remote = ?";
        $params[] = $is_remote;
        $types   .= "i";
    }
}

// WHERE clause
$whereSql = $filters ? ("WHERE " . implode(" AND ", $filters)) : "";

// 1) total count
$sqlCount = "SELECT COUNT(*) AS total FROM jobs j $whereSql";
$stmt = mysqli_prepare($conn, $sqlCount);
if (!$stmt) {
    ResponseHelper::error("Count query error: " . mysqli_error($conn), 500, "COUNT_QUERY_ERROR");
}

if ($types !== "") { 
    mysqli_stmt_bind_param($stmt, $types, ...$params); 
}

mysqli_stmt_execute($stmt);
$resCount = mysqli_stmt_get_result($stmt);
$totalRow = $resCount ? mysqli_fetch_assoc($resCount) : ['total' => 0];
$total = (int)($totalRow['total'] ?? 0);
mysqli_stmt_close($stmt);

// 2) page data
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
        FROM jobs j
        $whereSql
        ORDER BY j.{$sorting['sort_by']} {$sorting['sort_order']}
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    ResponseHelper::error("Query error: " . mysqli_error($conn), 500, "QUERY_ERROR");
}

// bind (filters + limit, offset)
if ($types !== "") {
    $bindTypes = $types . "ii";
    $bindParams = array_merge($params, [$pagination['limit'], $pagination['offset']]);
    mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $pagination['limit'], $pagination['offset']);
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
    "status"  => true,
    "meta"    => [
        "page"        => $pagination['page'],
        "limit"       => $pagination['limit'],
        "total"       => $total,
        "total_pages" => (int)ceil($total / max(1, $pagination['limit'])),
        "sort"        => $sorting['sort_by'],
        "order"       => $sorting['sort_order']
    ],
    "data"      => $jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
