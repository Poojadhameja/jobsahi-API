<?php
// interview_detail.php - Get Interview by ID with Panel
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
<<<<<<< HEAD
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate (you can restrict by role if needed, e.g. 'student' or 'recruiter')
// Example: authenticateJWT('student');
$decoded = authenticateJWT();  // no role restriction, just valid token
=======
header('Access-Control-Allow-Headers: Content-Type, Authorization');
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

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

$interview_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($interview_id <= 0) {
    echo json_encode(["message" => "Valid interview ID is required", "status" => false]);
    exit;
}

// ---- Fetch Interview ----
$sql = "SELECT id, application_id, scheduled_at, mode, location, status, feedback, created_at, modified_at, deleted_at 
        FROM interviews 
        WHERE id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $interview_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(["message" => "Interview not found", "status" => false]);
    exit;
}

$interview = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// ---- Fetch Interview Panel ----
$panel_sql = "SELECT id, interview_id, panelist_name
              FROM interview_panel 
              WHERE interview_id = ?";
$panel_stmt = mysqli_prepare($conn, $panel_sql);
mysqli_stmt_bind_param($panel_stmt, "i", $interview_id);
mysqli_stmt_execute($panel_stmt);
$panel_result = mysqli_stmt_get_result($panel_stmt);

$panels = [];
while ($row = mysqli_fetch_assoc($panel_result)) {
    $panels[] = $row;
}
mysqli_stmt_close($panel_stmt);

$interview['panel'] = $panels;

<<<<<<< HEAD
=======

>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289
echo json_encode([
    "message" => "Interview detail fetched successfully",
    "status" => true,
    "data" => $interview,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
