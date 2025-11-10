<?php
// get_saved_courses.php - Get student's saved courses list
require_once '../cors.php';

// ✅ Authenticate JWT (only student can access)
$decoded = authenticateJWT('student'); 

// Handle different key names from JWT payload safely
$user_id = null;
if (isset($decoded['id'])) {
    $user_id = $decoded['id'];
} elseif (isset($decoded['user_id'])) {
    $user_id = $decoded['user_id'];
} elseif (isset($decoded['student_id'])) {
    $user_id = $decoded['student_id'];
}

if (!$user_id) {
    echo json_encode([
        "message" => "Invalid token payload: student id missing",
        "status"  => false
    ]);
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
$student_id = $student_profile['id'];
mysqli_stmt_close($check_student_stmt);

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

// Get pagination parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Validate pagination
$limit = max(1, min(100, $limit)); // Limit between 1-100
$offset = max(0, $offset);

// Get saved courses with course details
$sql = "SELECT 
            c.id,
            c.title,
            c.description,
            c.duration,
            c.mode,
            c.fee,
            c.status,
            c.admin_action,
            c.created_at,
            sc.id as saved_course_id,
            sc.saved_at,
            cc.category_name,
            ip.institute_name
        FROM saved_courses sc
        INNER JOIN courses c ON sc.course_id = c.id
        LEFT JOIN course_category cc ON c.category_id = cc.id
        LEFT JOIN institute_profiles ip ON c.institute_id = ip.id
        WHERE sc.student_id = ?
        AND c.admin_action = 'approved'
        AND c.status = 'active'
        ORDER BY sc.saved_at DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Database error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($stmt, "iii", $student_id, $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$saved_courses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $saved_courses[] = [
        'saved_course_id' => intval($row['saved_course_id']),
        'course_id' => intval($row['id']),
        'title' => $row['title'],
        'description' => $row['description'],
        'duration' => $row['duration'],
        'mode' => $row['mode'],
        'fee' => floatval($row['fee']),
        'status' => $row['status'],
        'course_created_at' => $row['created_at'],
        'saved_at' => $row['saved_at'],
        'category_name' => $row['category_name'],
        'institute_name' => $row['institute_name']
    ];
}

mysqli_stmt_close($stmt);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM saved_courses sc
              INNER JOIN courses c ON sc.course_id = c.id
              WHERE sc.student_id = ?
              AND c.admin_action = 'approved'
              AND c.status = 'active'";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "i", $student_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_count = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($count_stmt);

mysqli_close($conn);

echo json_encode([
    "message" => "Saved courses retrieved successfully",
    "status" => true,
    "data" => $saved_courses,
    "pagination" => [
        "total" => intval($total_count),
        "limit" => $limit,
        "offset" => $offset,
        "has_more" => ($offset + $limit) < $total_count
    ],
    "timestamp" => date('Y-m-d H:i:s')
]);
?>


