<?php
// get-course.php - Fetch course details by ID with role-based visibility
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate user and get role
$user = authenticateJWT(['admin', 'student', 'institute']);
$role = $user['role'];
$user_id = $user['user_id'];

// ✅ Get course ID from query string
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($role === 'admin') {
    // ✅ Admin can see all courses
    $sql = "SELECT id, institute_id, category_id, title, description, duration, 
                   tagged_skills, batch_limit, instructor_name, mode, 
                   certification_allowed, media, fee, admin_action, status, 
                   created_at, updated_at
            FROM courses
            WHERE id = ?";
    $params = [$course_id];
    $param_types = "i";

} elseif ($role === 'institute') {
    // ✅ Institutes can see only their own courses
    $sql = "SELECT id, institute_id, category_id, title, description, duration, 
                   tagged_skills, batch_limit, instructor_name, mode, 
                   certification_allowed, media, fee, admin_action, status, 
                   created_at, updated_at
            FROM courses
            WHERE id = ? AND institute_id = ?";
    $params = [$course_id, $user_id];
    $param_types = "ii";

} else {
    // ✅ Students can see only approved courses
    $sql = "SELECT id, institute_id, category_id, title, description, duration, 
                   tagged_skills, batch_limit, instructor_name, mode, 
                   certification_allowed, media, fee, admin_action, status, 
                   created_at, updated_at
            FROM courses
            WHERE id = ? AND admin_action = 'approved'";
    $params = [$course_id];
    $param_types = "i";
}

// ✅ Prepare and execute query safely
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            http_response_code(200);
            echo json_encode([
                "status" => true,
                "course" => $row
            ]);
        } else {
            $message = match ($role) {
                'admin' => "Course not found.",
                'institute' => "Course not found or not owned by this institute.",
                default => "Course not found or not approved yet."
            };
            http_response_code(404);
            echo json_encode([
                "status" => false,
                "message" => $message
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Query execution failed: " . mysqli_error($conn)
        ]);
    }

    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to prepare statement: " . mysqli_error($conn)
    ]);
}

mysqli_close($conn);
?>
