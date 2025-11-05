<?php
// get_application.php - Fetch single application (Student/Recruiter/Admin access)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate (admin, recruiter, student can access)
$current_user = authenticateJWT(['admin', 'recruiter', 'student']); 

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(["message" => "Application ID is required and must be numeric", "status" => false]);
    exit;
}

$applicationId = intval($_GET['id']);
$role = strtolower($current_user['role']);
$user_id = intval($current_user['user_id'] ?? 0);

// ✅ Resolve profile IDs
$student_profile_id = null;
$recruiter_profile_id = null;

if ($role === 'student') {
    $q = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    if ($r->num_rows > 0) {
        $student_profile_id = $r->fetch_assoc()['id'];
    }
    $q->close();
}

if ($role === 'recruiter') {
    $q = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
    $q->bind_param("i", $user_id);
    $q->execute();
    $r = $q->get_result();
    if ($r->num_rows > 0) {
        $recruiter_profile_id = $r->fetch_assoc()['id'];
    }
    $q->close();
}

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

// ✅ Role-based restrictions
$bindTypes = "i";
$bindValues = [$applicationId];

if ($role === 'student' && $student_profile_id) {
    $sql .= " AND a.student_id = ?";
    $bindTypes .= "i";
    $bindValues[] = $student_profile_id;
} elseif ($role === 'recruiter' && $recruiter_profile_id) {
    $sql .= " AND j.recruiter_id = ?";
    $bindTypes .= "i";
    $bindValues[] = $recruiter_profile_id;
} elseif ($role !== 'admin') {
    echo json_encode(["message" => "Unauthorized access", "status" => false]);
    exit;
}

// ✅ Execute query
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . $conn->error, "status" => false]);
    exit;
}

$stmt->bind_param($bindTypes, ...$bindValues);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "message" => "Application fetched successfully",
        "status" => true,
        "data" => $row,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(["message" => "Application not found or not accessible", "status" => false]);
}

$stmt->close();
$conn->close();
?>
