<?php
// interview_detail.php - Get Interview by ID with Panel (with admin_action logic)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate (allow all roles but restrict visibility later)
$decoded = authenticateJWT(['admin', 'student']);  
$user_role = strtolower($decoded['role']);  // role from JWT payload

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

/*
===================================================
 Role-based Filtering Logic
===================================================
- Admin  → can see all interviews (pending/approved/rejected)
- Recruiter, Institute, Student → only see interviews with admin_action = 'approved'
===================================================
*/

if ($user_role === 'admin') {
    $sql = "SELECT id, application_id, scheduled_at, mode, location, status, feedback, 
                   created_at, modified_at, deleted_at, admin_action
            FROM interviews 
            WHERE id = ? LIMIT 1";
} else {
    $sql = "SELECT id, application_id, scheduled_at, mode, location, status, feedback, 
                   created_at, modified_at, deleted_at, admin_action
            FROM interviews 
            WHERE id = ? AND admin_action = 'approved' LIMIT 1";
}

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $interview_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(["message" => "Interview not found or not accessible", "status" => false]);
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

echo json_encode([
    "message" => "Interview detail fetched successfully",
    "status" => true,
    "role" => $user_role,
    "data" => $interview,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
