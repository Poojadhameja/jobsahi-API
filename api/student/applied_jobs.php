<?php
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

$limit = isset($_GET['limit']) ? max((int)$_GET['limit'], 1) : 50;
$offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

$sql = "
    SELECT
        a.id AS application_id,
        a.status,
        a.applied_at,
        j.id AS job_id,
        j.title AS job_title,
        j.location,
        j.job_type,
        j.salary_min,
        j.salary_max,
        j.experience_required,
        j.application_deadline
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    WHERE a.student_id = ?
      AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
      AND LOWER(a.admin_action) = 'approved'
    ORDER BY a.applied_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Prepare error: " . $conn->error]);
    exit;
}

$stmt->bind_param("iii", $student_profile_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $row['salary_min'] = $row['salary_min'] !== null ? (float)$row['salary_min'] : null;
    $row['salary_max'] = $row['salary_max'] !== null ? (float)$row['salary_max'] : null;
    $jobs[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "status" => true,
    "message" => "Applied jobs fetched successfully",
    "count" => count($jobs),
    "data" => $jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>

