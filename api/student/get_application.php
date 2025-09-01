<?php
<<<<<<< HEAD
// get_application.php - Fetch single application (Student/Authorized access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Allow only GET
=======
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

<<<<<<< HEAD
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate (student can access)
authenticateJWT('student'); // will allow if role is student (or higher if you extend logic)

=======
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["message" => "Application ID is required and must be numeric", "status" => false]);
    exit;
}

$applicationId = intval($_GET['id']);

include "../db.php";
if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

<<<<<<< HEAD
// Fetch application
=======
// Adjust query to match actual table columns
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
$sql = "SELECT 
            a.id,
            a.student_id,
            a.job_id,
            a.status,
            a.applied_at
        FROM applications a
        WHERE a.id = ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $applicationId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $application = $row;
} else {
    echo json_encode(["message" => "Application not found", "status" => false]);
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
<<<<<<< HEAD
?>
=======

?>
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
