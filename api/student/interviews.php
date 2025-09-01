<?php
// interviews.php - Fetch interviews (Student only)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate and allow "admin" and  "student" role
$decoded = authenticateJWT(['admin', 'student']);  // decoded JWT payload

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
$status = isset($_GET['status']) ? trim($_GET['status']) : null; // e.g., scheduled, completed, cancelled
$type = isset($_GET['type']) ? trim($_GET['type']) : null;       // upcoming or past
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// ---- Build Dynamic SQL ----
$sql = "SELECT id, application_id, scheduled_at, mode, location, status, feedback, created_at, modified_at, deleted_at
        FROM interviews 
        WHERE 1=1"; // always true, makes adding filters easier

$params = [];
$types = "";

// Filter by status
if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

// Filter by type (upcoming/past)
if (!empty($type)) {
    if ($type === 'upcoming') {
        $sql .= " AND scheduled_at >= NOW()";
    } elseif ($type === 'past') {
        $sql .= " AND scheduled_at < NOW()";
    }
}

$sql .= " ORDER BY scheduled_at ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// ---- Prepare & Execute ----
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ---- Fetch Results ----
$interviews = [];
while ($row = mysqli_fetch_assoc($result)) {
    $interviews[] = $row;
}

echo json_encode([
    "message" => "Interviews fetched successfully",
    "status" => true,
    "count" => count($interviews),
    "data" => $interviews,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
