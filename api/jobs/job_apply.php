<?php
// job_apply.php - Apply for a Job (Student only)
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate and allow only "student" role
$decoded = authenticateJWT('student');

// ✅ Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit;
}

// ✅ Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid JSON body"]);
    exit;
}

// ✅ Validate job_id
$job_id = isset($input['job_id']) ? intval($input['job_id']) : 0;
if ($job_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Valid job_id is required in body"]);
    exit;
}

// ✅ Validate cover_letter
if (empty($input['cover_letter'])) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Missing required field: cover_letter"]);
    exit;
}

// ✅ Get student_profile_id using user_id
$user_id = $decoded['id'] ?? $decoded['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Invalid token: user ID missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student_profile = $result->fetch_assoc();
$stmt->close();

if (!$student_profile) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Student profile not found"]);
    exit;
}
$student_id = intval($student_profile['id']);

// ✅ Check if job exists and is open
$stmt = $conn->prepare("SELECT id, status, application_deadline FROM jobs WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Job not found"]);
    exit;
}

if (!in_array(strtolower($job['status']), ['open', 'active'])) {
    echo json_encode(["status" => false, "message" => "This job is not open for applications"]);
    exit;
}

if (!empty($job['application_deadline']) && strtotime($job['application_deadline']) < time()) {
    echo json_encode(["status" => false, "message" => "Application deadline has passed"]);
    exit;
}

// ✅ Prevent duplicate application
$stmt = $conn->prepare("SELECT id FROM applications WHERE job_id = ? AND student_id = ?");
$stmt->bind_param("ii", $job_id, $student_id);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($exists) {
    echo json_encode(["status" => false, "message" => "You have already applied for this job"]);
    exit;
}

// ✅ Insert new application (job_selected default = 0)
$stmt = $conn->prepare("
    INSERT INTO applications 
        (job_id, student_id, cover_letter, job_selected, status, admin_action, applied_at)
    VALUES (?, ?, ?, 0, 'applied', 'approved', NOW())
");
$stmt->bind_param("iis", $job_id, $student_id, $input['cover_letter']);

if ($stmt->execute()) {
    $application_id = $stmt->insert_id;
    $stmt->close();

    // ✅ Fetch newly created application with job details
    $q = $conn->prepare("
        SELECT 
            a.id AS application_id,
            a.job_id,
            j.title AS job_title,
            j.location,
            a.student_id,
            a.cover_letter,
            a.status,
            a.job_selected,
            a.applied_at,
            a.admin_action
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        WHERE a.id = ?
    ");
    $q->bind_param("i", $application_id);
    $q->execute();
    $data = $q->get_result()->fetch_assoc();
    $q->close();

    http_response_code(201);
    echo json_encode([
        "status" => true,
        "message" => "Application submitted successfully",
        "application_id" => $application_id,
        "data" => $data
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Database error: " . $stmt->error
    ]);
    $stmt->close();
}

$conn->close();
?>
