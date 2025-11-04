<?php
// dashboard.php - Student Dashboard Counters API
require_once '../cors.php';

// ✅ Authenticate as student role
$decoded = authenticateJWT(['admin', 'student']);

// Handle possible payload key mismatch
$user_id = null;
if (isset($decoded['id'])) {
    $user_id = (int)$decoded['id'];
} elseif (isset($decoded['user_id'])) {
    $user_id = (int)$decoded['user_id'];
} else {
    echo json_encode([
        "message" => "Invalid token payload: student id missing",
        "status" => false
    ]);
    exit;
}

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(array("message" => "Only GET requests allowed", "status" => false));
    exit;
}

// Get student profile ID from user_id
$check_student_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
$check_student_stmt = mysqli_prepare($conn, $check_student_sql);
mysqli_stmt_bind_param($check_student_stmt, "i", $user_id);
mysqli_stmt_execute($check_student_stmt);
$student_result = mysqli_stmt_get_result($check_student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    echo json_encode([
        "message" => "Student profile not found. Please complete your profile.", 
        "status" => false,
        "user_id" => $user_id
    ]);
    mysqli_stmt_close($check_student_stmt);
    mysqli_close($conn);
    exit;
}

// Get the actual student profile ID
$student_profile = mysqli_fetch_assoc($student_result);
$student_profile_id = $student_profile['id'];
mysqli_stmt_close($check_student_stmt);

// Initialize counters
$counters = array(
    'applied' => 0,
    'saved' => 0,
    'recommended' => 0,
    'interviews' => 0
);

// 1. Count Applied Jobs
$applied_sql = "SELECT COUNT(*) as count FROM applications WHERE student_id = ?";
if ($applied_stmt = mysqli_prepare($conn, $applied_sql)) {
    mysqli_stmt_bind_param($applied_stmt, "i", $student_profile_id);
    mysqli_stmt_execute($applied_stmt);
    $applied_result = mysqli_stmt_get_result($applied_stmt);
    
    if ($row = mysqli_fetch_assoc($applied_result)) {
        $counters['applied'] = (int)$row['count'];
    }
    mysqli_stmt_close($applied_stmt);
}

// 2. Count Saved Jobs (using saved_jobs junction table)
$saved_sql = "SELECT COUNT(*) as count FROM saved_jobs WHERE student_id = ?";
if ($saved_stmt = mysqli_prepare($conn, $saved_sql)) {
    mysqli_stmt_bind_param($saved_stmt, "i", $student_profile_id);
    mysqli_stmt_execute($saved_stmt);
    $saved_result = mysqli_stmt_get_result($saved_stmt);
    
    if ($row = mysqli_fetch_assoc($saved_result)) {
        $counters['saved'] = (int)$row['count'];
    }
    mysqli_stmt_close($saved_stmt);
}

// 3. Count Recommendations
$recommended_sql = "SELECT COUNT(*) as count FROM job_recommendations WHERE student_id = ?";
if ($recommended_stmt = mysqli_prepare($conn, $recommended_sql)) {
    mysqli_stmt_bind_param($recommended_stmt, "i", $student_profile_id);
    mysqli_stmt_execute($recommended_stmt);
    $recommended_result = mysqli_stmt_get_result($recommended_stmt);
    
    if ($row = mysqli_fetch_assoc($recommended_result)) {
        $counters['recommended'] = (int)$row['count'];
    }
    mysqli_stmt_close($recommended_stmt);
}

// 4. Count Interviews
$interviews_sql = "SELECT COUNT(*) as count FROM applications 
                   WHERE student_id = ? AND status IN ('interview_scheduled', 'interview_completed', 'interview_pending')";
if ($interviews_stmt = mysqli_prepare($conn, $interviews_sql)) {
    mysqli_stmt_bind_param($interviews_stmt, "i", $student_profile_id);
    mysqli_stmt_execute($interviews_stmt);
    $interviews_result = mysqli_stmt_get_result($interviews_stmt);
    
    if ($row = mysqli_fetch_assoc($interviews_result)) {
        $counters['interviews'] = (int)$row['count'];
    }
    mysqli_stmt_close($interviews_stmt);
}

// Close DB
mysqli_close($conn);

// ✅ Response
echo json_encode(array(
    "message" => "Dashboard data retrieved successfully",
    "status" => true,
    "data" => $counters,
    "student_id" => $student_profile_id,
    "timestamp" => date('Y-m-d H:i:s')
));
?>
