<?php
<<<<<<< HEAD
// courses.php - Get course list with optional filters
=======
// courses.php - Get course list with pagination + sorting + filters
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

<<<<<<< HEAD
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate and check for admin or student role
authenticateJWT(['admin', 'student', 'institute']);

require_once '../db.php'; // DB connection

// Base SQL
$sql = "SELECT id, institute_id, title, description, duration, fee FROM courses WHERE 1=1";

// Filters (optional)
$params = [];
$types  = "";

// Filter by institute_id
if (!empty($_GET['institute_id'])) {
    $sql .= " AND institute_id = ?";
    $params[] = $_GET['institute_id'];
    $types   .= "i";
}

// Filter by min_fee
if (!empty($_GET['min_fee'])) {
    $sql .= " AND fee >= ?";
    $params[] = $_GET['min_fee'];
    $types   .= "d";
}

// Filter by max_fee
if (!empty($_GET['max_fee'])) {
    $sql .= " AND fee <= ?";
    $params[] = $_GET['max_fee'];
    $types   .= "d";
}

// Filter by duration
if (!empty($_GET['duration'])) {
    $sql .= " AND duration = ?";
    $params[] = $_GET['duration'];
    $types   .= "s";
}

// Search by keyword in title/description
if (!empty($_GET['q'])) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $keyword = "%" . $_GET['q'] . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $types   .= "ss";
}

// Prepare and execute
$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
=======
require_once '../db.php'; // mysqli $conn

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  echo json_encode(["status" => false, "message" => "Only GET allowed"]);
  exit;
}
if (!$conn) {
  echo json_encode(["status" => false, "message" => "DB connection failed: " . mysqli_connect_error()]);
  exit;
}

/**
 * Query params:
 * institute_id, min_fee, max_fee, duration, q
 * page (default 1), limit (default 20, max 100)
 * sort (id|fee|title), order (asc|desc)
 */

// pagination
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = ($limit > 0 && $limit <= 100) ? $limit : 20;
$offset = ($page - 1) * $limit;

// sorting (whitelist)
$allowedSort = ['id', 'fee', 'title'];
$sort  = isset($_GET['sort']) ? strtolower(trim($_GET['sort'])) : 'id';
$sort  = in_array($sort, $allowedSort, true) ? $sort : 'id';

$order = isset($_GET['order']) ? strtolower(trim($_GET['order'])) : 'desc';
$order = ($order === 'asc') ? 'ASC' : 'DESC';

// filters
$where  = [];
$params = [];
$types  = "";

// institute filter
if (isset($_GET['institute_id']) && $_GET['institute_id'] !== '') {
  $where[]  = "institute_id = ?";
  $params[] = (int)$_GET['institute_id'];
  $types   .= "i";
}

// fee range
if (isset($_GET['min_fee']) && $_GET['min_fee'] !== '') {
  $where[]  = "fee >= ?";
  $params[] = (float)$_GET['min_fee'];
  $types   .= "d";
}
if (isset($_GET['max_fee']) && $_GET['max_fee'] !== '') {
  $where[]  = "fee <= ?";
  $params[] = (float)$_GET['max_fee'];
  $types   .= "d";
}

// duration exact match (e.g. "3 Months")
if (!empty($_GET['duration'])) {
  $where[]  = "duration = ?";
  $params[] = $_GET['duration'];
  $types   .= "s";
}

// keyword search on title/description
if (!empty($_GET['q'])) {
  $where[]  = "(title LIKE ? OR description LIKE ?)";
  $kw       = "%".$_GET['q']."%";
  $params[] = $kw;
  $params[] = $kw;
  $types   .= "ss";
}

$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

// 1) total count
$sqlCount = "SELECT COUNT(*) AS total FROM courses $whereSql";
$stmt = mysqli_prepare($conn, $sqlCount);
if (!$stmt) {
  echo json_encode(["status" => false, "message" => "Count query error: ".mysqli_error($conn)]);
  exit;
}
if ($types !== "") { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$total = (int)($res ? (mysqli_fetch_assoc($res)['total'] ?? 0) : 0);
mysqli_stmt_close($stmt);

// 2) page data
$sql = "SELECT id, institute_id, title, description, duration, fee
        FROM courses
        $whereSql
        ORDER BY $sort $order
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
  echo json_encode(["status" => false, "message" => "Query error: ".mysqli_error($conn)]);
  exit;
}

if ($types !== "") {
  $bindTypes  = $types."ii";
  $bindParams = array_merge($params, [$limit, $offset]);
  mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindParams);
} else {
  mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$courses = [];
while ($row = mysqli_fetch_assoc($result)) {
<<<<<<< HEAD
    $courses[] = $row;
}

// Response
echo json_encode([
    "status" => true,
    "courses" => $courses
]);

mysqli_stmt_close($stmt);
mysqli_close($conn);

?>
=======
  $courses[] = $row;
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

// response
echo json_encode([
  "status"  => true,
  "message" => "Courses fetched",
  "meta"    => [
    "page"        => $page,
    "limit"       => $limit,
    "total"       => $total,
    "total_pages" => (int)ceil($total / max(1, $limit)),
    "sort"        => $sort,
    "order"       => $order
  ],
  "data"      => $courses,
  "timestamp" => date('Y-m-d H:i:s')
]);
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
