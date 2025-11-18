<?php
// interview-list.php - Get interview list for logged-in student (Student/Admin)
require_once '../cors.php';
require_once '../db.php';

$current_user = authenticateJWT(['student', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

$role = strtolower($current_user['role'] ?? '');
$user_id = $current_user['user_id'] ?? null;

$student_profile_id = null;

// ✅ Get student_profile_id for student role
if ($role === 'student') {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res) {
        echo json_encode(["status" => false, "message" => "Student profile not found"]);
        exit;
    }
    $student_profile_id = (int)$res['id'];
} else {
    // Admin can filter by student_id or student_user_id
    if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
        $student_profile_id = (int)$_GET['student_id'];
    } elseif (isset($_GET['student_user_id']) && is_numeric($_GET['student_user_id'])) {
        $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
            exit;
        }
        $student_user_id = (int)$_GET['student_user_id'];
        $stmt->bind_param("i", $student_user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            $student_profile_id = (int)$res['id'];
        }
    }

    if (!$student_profile_id) {
        echo json_encode(["status" => false, "message" => "student_id or student_user_id is required"]);
        exit;
    }
}

// ---- Fetch Filters from Query Params ----
$status = isset($_GET['status']) ? trim($_GET['status']) : null; // e.g., scheduled, completed, cancelled
$type   = isset($_GET['type'])   ? trim($_GET['type'])   : null; // upcoming or past
$limit  = isset($_GET['limit'])  ? max((int)$_GET['limit'], 1) : 50;
$offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

// ---- Build SQL - Minimal info for card display ----
$sql = "SELECT 
            i.id AS interview_id,
            i.scheduled_at,
            i.mode,
            i.platform_name,
            i.interview_link,
            i.location AS interview_location,
            i.status AS interview_status,
            j.id AS job_id,
            j.title AS job_title,
            j.salary_min,
            j.salary_max,
            a.applied_at,
            rp.company_name
        FROM interviews i
        INNER JOIN applications a ON i.application_id = a.id
        INNER JOIN jobs j ON a.job_id = j.id
        INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE a.student_id = ?
          AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
          AND LOWER(a.admin_action) = 'approved'
          AND (i.deleted_at IS NULL OR i.deleted_at = '0000-00-00 00:00:00')"; 

$params = [$student_profile_id];
$types  = "i";

// ✅ Role-based filter on admin_action for interviews
if ($role !== 'admin') {
    $sql .= " AND i.admin_action = 'approved'";
}

// ---- Additional Filters ----

// Filter by status
if (!empty($status)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Filter by type (upcoming/past)
if (!empty($type)) {
    if ($type === 'upcoming') {
        $sql .= " AND i.scheduled_at >= NOW()";
    } elseif ($type === 'past') {
        $sql .= " AND i.scheduled_at < NOW()";
    }
}

$sql .= " ORDER BY i.scheduled_at ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ---- Fetch Results - Minimal data for card display ----
// Interview-specific fields in separate object to avoid confusion
$interviews = [];
while ($row = $result->fetch_assoc()) {
    $interview = [
        "interview" => [
            "id" => (int)$row['interview_id'],
            "scheduled_at" => $row['scheduled_at'],
            "mode" => $row['mode'],
            "status" => $row['interview_status'],
            "location" => $row['mode'] === 'offline' ? $row['interview_location'] : null,
            "platform_name" => $row['mode'] === 'online' ? $row['platform_name'] : null,
            "interview_link" => $row['mode'] === 'online' ? $row['interview_link'] : null
        ],
        "job_id" => (int)$row['job_id'],
        "job" => [
            "id" => (int)$row['job_id'],
            "title" => $row['job_title'],
            "salary_min" => $row['salary_min'] ? floatval($row['salary_min']) : null,
            "salary_max" => $row['salary_max'] ? floatval($row['salary_max']) : null,
            "applied_at" => $row['applied_at']
        ],
        "company_name" => $row['company_name']
    ];
    
    $interviews[] = $interview;
}

$stmt->close();
$conn->close();

echo json_encode([
    "status" => true,
    "message" => "Interviews fetched successfully",
    "count" => count($interviews),
    "data" => $interviews,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>

