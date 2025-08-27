<?php
// dashboard.php - Student Dashboard Counters API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(array("message" => "Only GET requests allowed", "status" => false));
    exit;
}

// Get student_id from URL parameters or headers
$student_id = null;

// Method 1: Get from URL parameter (?student_id=123)
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
}

// Method 2: Get from Authorization header (if using JWT or session-based auth)
// Uncomment and modify this section if you're using token-based authentication
// /*
// $headers = getallheaders();
// if (isset($headers['Authorization'])) {
//     // Extract student_id from token or session
//     // $student_id = extract_student_id_from_token($headers['Authorization']);
// }
// */

// Validate student_id
if (!$student_id || $student_id <= 0) {
    echo json_encode(array("message" => "Valid student_id is required", "status" => false));
    exit;
}

include "../config.php";

// Verify student exists
$verify_sql = "SELECT id FROM users WHERE id = ? AND role = 'student'";
if ($verify_stmt = mysqli_prepare($conn, $verify_sql)) {
    mysqli_stmt_bind_param($verify_stmt, "i", $student_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if (mysqli_num_rows($verify_result) == 0) {
        echo json_encode(array("message" => "Student not found", "status" => false));
        mysqli_stmt_close($verify_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($verify_stmt);
} else {
    echo json_encode(array("message" => "Database error", "status" => false));
    mysqli_close($conn);
    exit;
}

// Initialize counters
$counters = array(
    'applied' => 0,
    'saved' => 0,
    'recommended' => 0,
    'interviews' => 0
);

// 1. Count Applied Jobs from applications table
$applied_sql = "SELECT COUNT(*) as count FROM applications WHERE student_id = ?";
if ($applied_stmt = mysqli_prepare($conn, $applied_sql)) {
    mysqli_stmt_bind_param($applied_stmt, "i", $student_id);
    mysqli_stmt_execute($applied_stmt);
    $applied_result = mysqli_stmt_get_result($applied_stmt);
    
    if ($row = mysqli_fetch_assoc($applied_result)) {
        $counters['applied'] = (int)$row['count'];
    }
    mysqli_stmt_close($applied_stmt);
}

// 2. Count Saved Jobs from saved_jobs table (excluding deleted ones)
$saved_sql = "SELECT COUNT(*) as count FROM saved_jobs WHERE student_id = ? AND deleted_at IS NULL";
if ($saved_stmt = mysqli_prepare($conn, $saved_sql)) {
    mysqli_stmt_bind_param($saved_stmt, "i", $student_id);
    mysqli_stmt_execute($saved_stmt);
    $saved_result = mysqli_stmt_get_result($saved_stmt);
    
    if ($row = mysqli_fetch_assoc($saved_result)) {
        $counters['saved'] = (int)$row['count'];
    }
    mysqli_stmt_close($saved_stmt);
}

// 3. Count Job Recommendations from job_recommendations table
$recommended_sql = "SELECT COUNT(*) as count FROM job_recommendations WHERE student_id = ?";
if ($recommended_stmt = mysqli_prepare($conn, $recommended_sql)) {
    mysqli_stmt_bind_param($recommended_stmt, "i", $student_id);
    mysqli_stmt_execute($recommended_stmt);
    $recommended_result = mysqli_stmt_get_result($recommended_stmt);
    
    if ($row = mysqli_fetch_assoc($recommended_result)) {
        $counters['recommended'] = (int)$row['count'];
    }
    mysqli_stmt_close($recommended_stmt);
}

// 4. Count Interviews from applications table (assuming status indicates interview stage)
// Modify the status values based on your actual application status values
$interviews_sql = "SELECT COUNT(*) as count FROM applications 
                   WHERE student_id = ? AND status IN ('interview_scheduled', 'interview_completed', 'interview_pending')";
if ($interviews_stmt = mysqli_prepare($conn, $interviews_sql)) {
    mysqli_stmt_bind_param($interviews_stmt, "i", $student_id);
    mysqli_stmt_execute($interviews_stmt);
    $interviews_result = mysqli_stmt_get_result($interviews_stmt);
    
    if ($row = mysqli_fetch_assoc($interviews_result)) {
        $counters['interviews'] = (int)$row['count'];
    }
    mysqli_stmt_close($interviews_stmt);
}

// Close database connection
mysqli_close($conn);

// Return the dashboard counters
echo json_encode(array(
    "message" => "Dashboard data retrieved successfully",
    "status" => true,
    "data" => $counters,
    "student_id" => $student_id,
    "timestamp" => date('Y-m-d H:i:s')
));
?>