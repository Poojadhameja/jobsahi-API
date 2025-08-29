<?php
// courses.php - Get course list with optional filters
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

require_once '../config.php'; // DB connection

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
