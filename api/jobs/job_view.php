<?php
// job_view.php - Record Job View API
require_once '../cors.php';

// ✅ Authenticate (student + admin + recruiter + institute)
$decoded = authenticateJWT(['student', 'admin', 'recruiter', 'institute']); // decoded JWT payload

// ✅ Get job_id ONLY from URL (?id=...)
$job_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
if (!$job_id) {
    echo json_encode(["message" => "Job ID missing in URL", "status" => false]);
    exit;
}

// ✅ Extract user info from JWT
$user_id   = isset($decoded['id']) ? (int)$decoded['id'] : (int)$decoded['user_id'];
$user_role = strtolower($decoded['role'] ?? '');

if (empty($user_id)) {
    echo json_encode(["message" => "❌ JWT missing user ID", "status" => false]);
    exit;
}

// ✅ Get student_profile_id (for students)
$student_id = null;
if ($user_role === 'student') {
    $get_student_sql = "SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1";
    $get_stmt = mysqli_prepare($conn, $get_student_sql);
    mysqli_stmt_bind_param($get_stmt, "i", $user_id);
    mysqli_stmt_execute($get_stmt);
    $res = mysqli_stmt_get_result($get_stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $student_id = (int)$row['id'];
    }
    mysqli_stmt_close($get_stmt);

    if (!$student_id) {
        echo json_encode(["message" => "Student profile not found for this user", "status" => false]);
        exit;
    }
}

// ✅ JOB VISIBILITY RULE
if ($user_role === 'admin') {
    $check_job_sql = "SELECT id, title, status, admin_action FROM jobs WHERE id = ?";
} else {
    $check_job_sql = "SELECT id, title, status, admin_action 
                      FROM jobs 
                      WHERE id = ? AND admin_action = 'approved'";
}

$check_stmt = mysqli_prepare($conn, $check_job_sql);
mysqli_stmt_bind_param($check_stmt, "i", $job_id);

if (!$check_stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_execute($check_stmt);
$job_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($job_result) === 0) {
    mysqli_stmt_close($check_stmt);
    mysqli_close($conn);
    echo json_encode([
        "message" => "Job not available (role: $user_role)",
        "status" => false
    ]);
    exit;
}

$job_data = mysqli_fetch_assoc($job_result);
mysqli_stmt_close($check_stmt);

// ✅ Only active jobs allowed
if ($job_data['status'] !== 'open') {
    mysqli_close($conn);
    echo json_encode([
        "message" => "Job is not active (status: " . $job_data['status'] . ")",
        "status" => false
    ]);
    exit;
}

// ✅ USER CHECK
$check_user_sql = "SELECT id, user_name, email, role FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $check_user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);

if (mysqli_num_rows($user_result) === 0) {
    mysqli_stmt_close($user_stmt);
    mysqli_close($conn);
    echo json_encode(["message" => "User with ID $user_id not found", "status" => false]);
    exit;
}

$user_data = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

// ✅ JOB VIEW LOGIC (record only if student)
if ($user_role === 'student') {
    $today = date('Y-m-d');
    $check_view_sql = "SELECT id FROM job_views WHERE job_id = ? AND student_id = ? AND DATE(viewed_at) = ?";
    $view_check_stmt = mysqli_prepare($conn, $check_view_sql);
    mysqli_stmt_bind_param($view_check_stmt, "iis", $job_id, $student_id, $today);
    mysqli_stmt_execute($view_check_stmt);
    $existing_view = mysqli_stmt_get_result($view_check_stmt);

    if (mysqli_num_rows($existing_view) > 0) {
        mysqli_stmt_close($view_check_stmt);
        mysqli_close($conn);
        echo json_encode([
            "message" => "Job view already recorded for today",
            "status" => true,
            "data" => [
                "job_id" => $job_id,
                "student_id" => $student_id,
                "already_viewed_today" => true
            ],
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    mysqli_stmt_close($view_check_stmt);

    // ✅ Insert new view (with admin_action default = 'approved')
    $current_datetime = date('Y-m-d H:i:s');
    $admin_action = 'approved';
    $insert_sql = "INSERT INTO job_views (job_id, student_id, viewed_at, admin_action) VALUES (?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "iiss", $job_id, $student_id, $current_datetime, $admin_action);

    if (mysqli_stmt_execute($insert_stmt)) {
        $view_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_stmt);
        mysqli_close($conn);

        echo json_encode([
            "message" => "Job view recorded successfully",
            "status" => true,
            "data" => [
                "view_id" => $view_id,
                "job_id" => $job_id,
                "student_id" => $student_id,
                "job_title" => $job_data['title'],
                "student_name" => $user_data['user_name'],
                "role" => $user_data['role'],
                "status" => $job_data['status'],
                "admin_action" => $admin_action,
                "viewed_at" => $current_datetime
            ],
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        exit;
    } else {
        mysqli_stmt_close($insert_stmt);
        mysqli_close($conn);
        echo json_encode([
            "message" => "Failed to record job view: " . mysqli_error($conn),
            "status" => false
        ]);
        exit;
    }
}

// ✅ For non-student users, just return job info
mysqli_close($conn);
echo json_encode([
    "message" => "Job fetched successfully (view not recorded for non-student roles)",
    "status" => true,
    "data" => [
        "job_id" => $job_data['id'],
        "job_title" => $job_data['title'],
        "status" => $job_data['status'],
        "admin_action" => $job_data['admin_action']
    ]
]);
?>
