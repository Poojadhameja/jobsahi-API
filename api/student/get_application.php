<?php
// get_application.php - Fetch single application (Student/Recruiter/Admin access)
require_once '../cors.php';

// ✅ Authenticate (admin, recruiter, student can access)
$current_user = authenticateJWT(['admin', 'recruiter', 'student']); 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["message" => "Application ID is required and must be numeric", "status" => false]);
    exit;
}

$applicationId = intval($_GET['id']);
$role = $current_user['role']; 
$user_id = $current_user['user_id'] ?? 0;


// ✅ Base query
$sql = "
    SELECT 
        a.id AS application_id,
        a.student_id,
        a.job_id,
        a.status,
        a.applied_at,
        a.resume_link,
        a.cover_letter,
        a.created_at,
        a.modified_at,
        j.title AS job_title,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.recruiter_id
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.id = ?
";

// Role-based restrictions
if ($role === 'student') {
    // Student can see only their own application
    $sql .= " AND a.student_id = ?";
    $bindTypes = "ii";
    $bindValues = [$applicationId, $user_id];
} elseif ($role === 'recruiter') {
    // Recruiter can see only applications for their jobs
    $sql .= " AND j.recruiter_id = ?";
    $bindTypes = "ii";
    $bindValues = [$applicationId, $user_id];
} else {
    // Admin can see all
    $bindTypes = "i";
    $bindValues = [$applicationId];
}

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
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
