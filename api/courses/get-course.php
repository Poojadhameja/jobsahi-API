<?php
// get-course.php - Fetch course details by ID with role-based visibility
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
require_once '../db.php'; // database connection

// Authenticate user and get role
$user = authenticateJWT(['admin','student']); // Returns user info with 'role'

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Course ID is required"
        ]);
        exit;
    }

    $course_id = intval($_GET['id']);
    $role = $user['role'];

    // Build role-based query
    if ($role === 'admin') {
        // Admin can see all courses (pending, approved, rejected, etc.)
        $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action
                FROM courses
                WHERE id = ?";
        $params = [$course_id];
        $param_types = "i";
    } else {
        // Students and other roles can see only approved courses
        $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action
                FROM courses
                WHERE id = ? AND admin_action = ?";
        $params = [$course_id, 'approved']; // Changed from 'approval' to 'approved'
        $param_types = "is";
    }

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind parameters dynamically based on role
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
                // More specific error messages based on role
                if ($role === 'admin') {
                    $message = "Course not found";
                } else {
                    $message = "Course not found or not approved yet";
                }
                
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
} else {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Method not allowed"
    ]);
}

mysqli_close($conn);
?>