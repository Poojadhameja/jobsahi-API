<?php
<<<<<<< HEAD
// jobs.php - Job Listings API (Student access with JWT)
=======
// jobs.php - Job Listings API (with pagination + sorting)
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

<<<<<<< HEAD
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate and allow only "student" role
$decoded = authenticateJWT('student');  // decoded JWT payload

// Check request method
=======
// Only GET
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

require_once "../db.php";
if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

<<<<<<< HEAD
// Collect filters from query params
$filters = [];
$params = [];
$types  = "";

// Keyword search (title/description)
if (!empty($_GET['keyword'])) {
    $filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $kw = "%" . $_GET['keyword'] . "%";
    $params[] = $kw;
    $params[] = $kw;
    $types   .= "ss";
}

// Location filter
=======
/**
 * Query params:
 * keyword, location, job_type, status, is_remote
 * page (default 1), limit (default 20, max 100)
 * sort (created_at|salary_min|salary_max|title|application_deadline), order (asc|desc)
 */

// pagination
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = ($limit > 0 && $limit <= 100) ? $limit : 20;
$offset = ($page - 1) * $limit;

// sorting (whitelist)
$allowedSort = ['created_at','salary_min','salary_max','title','application_deadline'];
$sort  = isset($_GET['sort']) ? strtolower(trim($_GET['sort'])) : 'created_at';
$sort  = in_array($sort, $allowedSort, true) ? $sort : 'created_at';

$order = isset($_GET['order']) ? strtolower(trim($_GET['order'])) : 'desc';
$order = ($order === 'asc') ? 'ASC' : 'DESC';

// filters
$filters = [];
$params  = [];
$types   = "";

// keyword (title/description LIKE)
if (!empty($_GET['keyword'])) {
    $filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $kw = "%" . $_GET['keyword'] . "%";
    $params[] = $kw; $params[] = $kw;
    $types   .= "ss";
}

// location (exact match; change to LIKE if you want partial)
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
if (!empty($_GET['location'])) {
    $filters[] = "j.location = ?";
    $params[] = $_GET['location'];
    $types   .= "s";
}

<<<<<<< HEAD
// Job type filter
=======
// job_type (enum: full_time, part_time, internship, contract)
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
if (!empty($_GET['job_type'])) {
    $filters[] = "j.job_type = ?";
    $params[] = $_GET['job_type'];
    $types   .= "s";
}

<<<<<<< HEAD
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
=======
// status (enum: open, closed, paused) — default open if not provided
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $filters[] = "j.status = ?";
    $params[] = $_GET['status'];
    $types   .= "s";
} else {
    $filters[] = "j.status = 'open'";
}

// is_remote (0/1)
if (isset($_GET['is_remote']) && $_GET['is_remote'] !== '') {
    $filters[] = "j.is_remote = ?";
    $params[] = (int)$_GET['is_remote'];
    $types   .= "i";
}

// WHERE clause
$whereSql = $filters ? ("WHERE " . implode(" AND ", $filters)) : "";

// 1) total count
$sqlCount = "SELECT COUNT(*) AS total FROM jobs j $whereSql";
$stmt = mysqli_prepare($conn, $sqlCount);
if (!$stmt) {
    echo json_encode(["message" => "Count query error: " . mysqli_error($conn), "status" => false]);
    exit;
}
if ($types !== "") { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$resCount = mysqli_stmt_get_result($stmt);
$totalRow = $resCount ? mysqli_fetch_assoc($resCount) : ['total' => 0];
$total = (int)($totalRow['total'] ?? 0);
mysqli_stmt_close($stmt);

// 2) page data
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
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
            j.created_at,
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS views
<<<<<<< HEAD
        FROM jobs j";

if (!empty($filters)) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}

$sql .= " ORDER BY j.created_at DESC";

// Prepare statement
=======
        FROM jobs j
        $whereSql
        ORDER BY j.$sort $order
        LIMIT ? OFFSET ?";

>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

<<<<<<< HEAD
// Bind filters
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
=======
// bind (filters + limit, offset)
if ($types !== "") {
    $bindTypes = $types . "ii";
    $bindParams = array_merge($params, [$limit, $offset]);
    mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$jobs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $jobs[] = $row;
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

<<<<<<< HEAD
// Return JSON
echo json_encode([
    "message" => "Jobs fetched successfully",
    "status" => true,
    "count" => count($jobs),
    "data" => $jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
=======
// response
echo json_encode([
    "message" => "Jobs fetched successfully",
    "status"  => true,
    "meta"    => [
        "page"        => $page,
        "limit"       => $limit,
        "total"       => $total,
        "total_pages" => (int)ceil($total / max(1, $limit)),
        "sort"        => $sort,
        "order"       => $order
    ],
    "data"      => $jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
