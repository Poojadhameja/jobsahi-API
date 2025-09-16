<?php
// courses.php - Get course list with optional filters and admin_action visibility
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate user and get role
$user = authenticateJWT(['admin', 'student', 'institute']); // returns user info including role
$user_role = $user['role'] ?? 'student'; // fallback to student if role not found

require_once '../db.php'; // DB connection

// Base SQL
$sql = "SELECT id, institute_id, title, description, duration, fee, admin_action FROM courses WHERE 1=1";

// Role-based visibility
$params = [];
$types = "";

if ($user_role !== 'admin') {
    // Non-admin users see only approved courses
    $sql .= " AND admin_action = ?";
    $params[] = 'approval';
    $types .= "s";
} else {
    // Admin sees both pending and approved courses
    $sql .= " AND admin_action IN (?, ?)";
    $params[] = 'pending';
    $params[] = 'approval';
    $types .= "ss";
}

// Optional filters

// Filter by institute_id
if (!empty($_GET['institute_id'])) {
    $sql .= " AND institute_id = ?";
    $params[] = $_GET['institute_id'];
    $types .= "i";
}

// Filter by min_fee
if (!empty($_GET['min_fee'])) {
    $sql .= " AND fee >= ?";
    $params[] = $_GET['min_fee'];
    $types .= "d";
}

// Filter by max_fee
if (!empty($_GET['max_fee'])) {
    $sql .= " AND fee <= ?";
    $params[] = $_GET['max_fee'];
    $types .= "d";
}

// Filter by duration
if (!empty($_GET['duration'])) {
    $sql .= " AND duration = ?";
    $params[] = $_GET['duration'];
    $types .= "s";
}

// Search by keyword in title/description
if (!empty($_GET['q'])) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $keyword = "%" . $_GET['q'] . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "ss";
}

// Prepare and execute
$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$courses = [];
while ($row = mysqli_fetch_assoc($result)) {
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
