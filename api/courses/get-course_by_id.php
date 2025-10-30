<?php
// get-course.php - Fetch course details by ID with role-based visibility
require_once '../cors.php';

// Authenticate user and get role
$user = authenticateJWT(['admin','student','institute']); // ✅ Fix: include 'institute' role
$role = $user['role'];  // ✅ Fix: define $role

// Get course ID from query string
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;  // ✅ Fix: define $course_id

if ($role === 'admin') {
    // Admin can see all courses
    $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action
            FROM courses
            WHERE id = ?";
    $params = [$course_id];
    $param_types = "i";
} else {
    // Students can see only approved courses
    $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action
            FROM courses
            WHERE id = ? AND admin_action = ?";
    $params = [$course_id, 'approved'];
    $param_types = "is";
}

if ($stmt = mysqli_prepare($conn, $sql)) {
    // Bind parameters dynamically
    if ($role === 'admin') {
        mysqli_stmt_bind_param($stmt, $param_types, $params[0]);
    } else {
        mysqli_stmt_bind_param($stmt, $param_types, $params[0], $params[1]);
    }

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            http_response_code(200);
            echo json_encode([
                "status" => true,
                "course" => $row
            ]);
        } else {
            $message = $role === 'admin'
                ? "Course not found"
                : "Course not found or not approved yet";

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
