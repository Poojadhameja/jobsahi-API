<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
require_once '../helpers/response_helper.php';
require_once '../helpers/rate_limiter.php';

// Apply rate limiting (200 requests per hour for admin)
RateLimiter::apply('get_all_users', 200, 3600);

// Authenticate and check for admin role
authenticateJWT('admin');

include "../db.php";

// Get pagination and sorting parameters
$pagination = ResponseHelper::getPaginationParams(20, 100);
$sorting = ResponseHelper::getSortingParams(['id', 'name', 'email', 'role', 'created_at', 'is_verified'], 'id', 'DESC');

// Get total count first
$count_sql = "SELECT COUNT(*) as total FROM users";
$count_result = mysqli_query($conn, $count_sql);

if (!$count_result) {
    ResponseHelper::error("Database query failed: " . mysqli_error($conn), 500, "DB_ERROR");
}

$total_count = mysqli_fetch_assoc($count_result)['total'];

// Main query with pagination
$sql = "SELECT id, name, email, role, phone_number, is_verified, created_at 
        FROM users 
        ORDER BY {$sorting['sort_by']} {$sorting['sort_order']} 
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    ResponseHelper::error("Database prepare failed: " . mysqli_error($conn), 500, "PREPARE_ERROR");
}

mysqli_stmt_bind_param($stmt, "ii", $pagination['limit'], $pagination['offset']);

if (!mysqli_stmt_execute($stmt)) {
    ResponseHelper::error("Query execution failed: " . mysqli_stmt_error($stmt), 500, "EXECUTE_ERROR");
}

$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    ResponseHelper::error("Database query failed: " . mysqli_error($conn), 500, "QUERY_ERROR");
}

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

// Send paginated response with sorting info
$response_data = [
    'users' => $users,
    'sorting' => $sorting
];

ResponseHelper::paginated(
    $response_data, 
    $pagination['page'], 
    $pagination['limit'], 
    $total_count, 
    "Users fetched successfully"
);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
