<?php
// save_course.php - Save course for student (JWT required)
require_once '../cors.php';

// ✅ Authenticate JWT (only student can access)
$decoded = authenticateJWT('student'); 

// Handle different key names from JWT payload safely
$student_id = null;
if (isset($decoded['id'])) {
    $student_id = $decoded['id'];
} elseif (isset($decoded['user_id'])) {
    $student_id = $decoded['user_id'];
} elseif (isset($decoded['student_id'])) {
    $student_id = $decoded['student_id'];
}

if (!$student_id) {
    echo json_encode([
        "message" => "Invalid token payload: student id missing",
        "status"  => false
    ]);
    exit;
}

// Get input data - Handle both JSON and form data
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
$course_id = null;

if (strpos($content_type, "application/json") !== false) {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["message" => "Invalid JSON format: " . json_last_error_msg(), "status" => false]);
        exit;
    }
    
    $course_id = isset($input['course_id']) ? intval($input['course_id']) : null;
} else {
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
}

// Validation
if (!$course_id || $course_id <= 0) {
    echo json_encode([
        "message" => "Course ID is required and must be a positive integer", 
        "status" => false,
        "received_course_id" => $course_id
    ]);
    exit;
}

// ✅ Check if student exists in student_profiles table
$check_student_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
$check_student_stmt = mysqli_prepare($conn, $check_student_sql);
mysqli_stmt_bind_param($check_student_stmt, "i", $student_id);
mysqli_stmt_execute($check_student_stmt);
$student_result = mysqli_stmt_get_result($check_student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    echo json_encode([
        "message" => "Student profile not found. Please complete your profile.", 
        "status" => false,
        "student_id" => $student_id
    ]);
    mysqli_stmt_close($check_student_stmt);
    mysqli_close($conn);
    exit;
}

// Get the actual student profile ID
$student_profile = mysqli_fetch_assoc($student_result);
$student_profile_id = $student_profile['id'];
mysqli_stmt_close($check_student_stmt);

// ✅ Check if course exists first
$check_course_exists_sql = "SELECT id, title, status, admin_action FROM courses WHERE id = ?";
$check_course_exists_stmt = mysqli_prepare($conn, $check_course_exists_sql);
mysqli_stmt_bind_param($check_course_exists_stmt, "i", $course_id);
mysqli_stmt_execute($check_course_exists_stmt);
$course_exists_result = mysqli_stmt_get_result($check_course_exists_stmt);

if (mysqli_num_rows($course_exists_result) === 0) {
    echo json_encode([
        "message" => "Course not found",
        "status" => false,
        "course_id" => $course_id,
        "reason" => "Course with this ID does not exist"
    ]);
    mysqli_stmt_close($check_course_exists_stmt);
    mysqli_close($conn);
    exit;
}

$course_info = mysqli_fetch_assoc($course_exists_result);
mysqli_stmt_close($check_course_exists_stmt);

// ✅ Check if course is active and approved
if ($course_info['status'] !== 'active') {
    echo json_encode([
        "message" => "Course is not available for saving",
        "status" => false,
        "course_id" => $course_id,
        "course_title" => $course_info['title'],
        "reason" => "Course status is not active. Current status: " . $course_info['status']
    ]);
    mysqli_close($conn);
    exit;
}

if ($course_info['admin_action'] !== 'approved') {
    echo json_encode([
        "message" => "Course is not available for saving",
        "status" => false,
        "course_id" => $course_id,
        "course_title" => $course_info['title'],
        "reason" => "Course is not approved by admin. Current status: " . $course_info['admin_action']
    ]);
    mysqli_close($conn);
    exit;
}

// Course is valid, proceed with saving
$course_data = $course_info;

// ✅ Check if already saved by this student
$check_saved_sql = "SELECT id FROM saved_courses WHERE student_id = ? AND course_id = ?";
$check_saved_stmt = mysqli_prepare($conn, $check_saved_sql);
mysqli_stmt_bind_param($check_saved_stmt, "ii", $student_profile_id, $course_id);
mysqli_stmt_execute($check_saved_stmt);
$saved_result = mysqli_stmt_get_result($check_saved_stmt);

if (mysqli_num_rows($saved_result) > 0) {
    echo json_encode([
        "message" => "Course is already saved by you", 
        "status" => false,
        "already_saved" => true
    ]);
    mysqli_stmt_close($check_saved_stmt);
    mysqli_close($conn);
    exit;
}
mysqli_stmt_close($check_saved_stmt);

// ✅ Insert into saved_courses table
$insert_sql = "INSERT INTO saved_courses (student_id, course_id, saved_at) VALUES (?, ?, NOW())";
$insert_stmt = mysqli_prepare($conn, $insert_sql);
mysqli_stmt_bind_param($insert_stmt, "ii", $student_profile_id, $course_id);

if (mysqli_stmt_execute($insert_stmt)) {
    $saved_course_id = mysqli_insert_id($conn);
    
    // Fetch saved course details with course info
    $get_saved_sql = "SELECT sc.id, sc.student_id, sc.course_id, sc.saved_at,
                             c.title, c.duration, c.mode, c.fee
                      FROM saved_courses sc
                      JOIN courses c ON sc.course_id = c.id
                      WHERE sc.id = ?";
    $get_saved_stmt = mysqli_prepare($conn, $get_saved_sql);
    
    if ($get_saved_stmt) {
        mysqli_stmt_bind_param($get_saved_stmt, "i", $saved_course_id);
        mysqli_stmt_execute($get_saved_stmt);
        $saved_course_result = mysqli_stmt_get_result($get_saved_stmt);
        $saved_course_data = mysqli_fetch_assoc($saved_course_result);

        echo json_encode([
            "message" => "Course saved successfully",
            "status" => true,
            "data" => [
                "saved_course_id" => intval($saved_course_data['id']),
                "course_id" => intval($saved_course_data['course_id']),
                "course_title" => $saved_course_data['title'],
                "student_id" => intval($saved_course_data['student_id']),
                "saved_at" => $saved_course_data['saved_at']
            ],
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        mysqli_stmt_close($get_saved_stmt);
    } else {
        echo json_encode([
            "message" => "Course saved successfully but couldn't fetch details",
            "status" => true,
            "saved_course_id" => $saved_course_id,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    }
} else {
    echo json_encode(["message" => "Failed to save course: " . mysqli_stmt_error($insert_stmt), "status" => false]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>


