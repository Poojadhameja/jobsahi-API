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
        // Admin can see all courses
        $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action 
                FROM courses 
                WHERE id = ? AND (admin_action = 'pending' OR admin_action = 'approval')";
    } else {
        // Other roles can see only approved courses
        $sql = "SELECT id, institute_id, title, description, duration, fee, admin_action 
                FROM courses 
                WHERE id = ? AND admin_action = 'approval'";
    }

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $course_id);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                http_response_code(200);
                echo json_encode([
                    "status" => true,
                    "course" => $row
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    "status" => false,
                    "message" => "Course not found or not visible to your role"
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "message" => "Query execution failed"
            ]);
        }
        mysqli_stmt_close($stmt);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to prepare statement"
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
