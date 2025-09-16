<?php
include '../CORS.php';
require_once '../helpers/response_helper.php';
require_once '../helpers/rate_limiter.php';

// Apply rate limiting (100 requests per hour)
RateLimiter::apply('search_users', 100, 3600);

// Get search value from GET or POST JSON
if (isset($_GET['search'])) {
    $search_value = trim($_GET['search']);
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    $search_value = isset($data['search']) ? trim($data['search']) : '';
}

// Get pagination parameters
$pagination = ResponseHelper::getPaginationParams(20, 100);

if (empty($search_value)) {
    ResponseHelper::error("Search value is required", 400, "SEARCH_REQUIRED");
}

// Validate search input length
if (strlen($search_value) < 2) {
    ResponseHelper::error("Search value must be at least 2 characters long", 400, "SEARCH_TOO_SHORT");
}

include "../db.php";

// Use prepared statement to prevent SQL injection
$sql = "SELECT id, name, email, role, phone_number, is_verified, created_at
        FROM users 
        WHERE id LIKE ? 
           OR name LIKE ? 
           OR email LIKE ? 
           OR phone_number LIKE ?
        ORDER BY name ASC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    ResponseHelper::error("Database error: " . mysqli_error($conn), 500, "DB_ERROR");
}

// Bind parameters with wildcards for LIKE search
$search_pattern = "%{$search_value}%";
mysqli_stmt_bind_param($stmt, "ssssii", $search_pattern, $search_pattern, $search_pattern, $search_pattern, $pagination['limit'], $pagination['offset']);

if (!mysqli_stmt_execute($stmt)) {
    ResponseHelper::error("Query execution failed: " . mysqli_stmt_error($stmt), 500, "QUERY_ERROR");
}

$result = mysqli_stmt_get_result($stmt);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users 
              WHERE id LIKE ? OR name LIKE ? OR email LIKE ? OR phone_number LIKE ?";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "ssss", $search_pattern, $search_pattern, $search_pattern, $search_pattern);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_count = mysqli_fetch_assoc($count_result)['total'];

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

// Send paginated response
ResponseHelper::paginated(
    $users, 
    $pagination['page'], 
    $pagination['limit'], 
    $total_count, 
    count($users) > 0 ? "Users found successfully" : "No users found"
);

mysqli_stmt_close($stmt);
mysqli_stmt_close($count_stmt);
mysqli_close($conn);
?>
