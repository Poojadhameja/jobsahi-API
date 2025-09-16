<?php
include '../CORS.php';
// Allow only GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate (admin, student can access)
$current_user = authenticateJWT(['admin', 'student']); 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["message" => "Application ID is required and must be numeric", "status" => false]);
    exit;
}

$applicationId = intval($_GET['id']);
$role = $current_user['role']; // role from JWT payload

include "../db.php";
if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ✅ Role-based query
$sql = "
    SELECT 
        a.id,
        a.student_id,
        a.job_id,
        a.status,
        a.applied_at,
        a.admin_action
    FROM applications a
    WHERE a.id = ?
      AND (
            (? = 'admin')
            OR (? IN ('recruiter', 'institute', 'student') AND a.admin_action = 'approved')
          )
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($stmt, "iss", $applicationId, $role, $role);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $application = $row;
} else {
    echo json_encode(["message" => "Application not found or not accessible", "status" => false]);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode([
    "message" => "Application fetched successfully",
    "status" => true,
    "data" => $application,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
