<?php
// courses_feedback.php - Submit and fetch course feedback with role-based visibility
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';
require_once '../db.php';  // DB connection

// Parse request method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Authenticate and check for student role
    authenticateJWT('student');

    // Validate course_id from URL: /api/v1/courses/{id}/feedback
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid or missing course ID"
        ]);
        exit();
    }
    $course_id = intval($_GET['id']);

    // Parse JSON body
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['student_id']) || !isset($data['rating']) || !isset($data['feedback'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Required fields: student_id, rating, feedback"
        ]);
        exit();
    }

    $student_id = intval($data['student_id']);
    $rating = intval($data['rating']);
    $feedback = trim($data['feedback']);

    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Rating must be between 1 and 5"
        ]);
        exit();
    }

    // Insert into course_feedback with default admin_action = 'pending'
    $sql = "INSERT INTO course_feedback (course_id, student_id, rating, feedback, admin_action, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiis", $course_id, $student_id, $rating, $feedback);

        if (mysqli_stmt_execute($stmt)) {
            http_response_code(201);
            echo json_encode([
                "status" => true,
                "message" => "Feedback submitted successfully"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => false,
                "message" => "Database error: " . mysqli_error($conn)
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
} elseif ($method === 'GET') {
    // Authenticate any role (admin, student, recruiter, institute)
    $user_role = authenticateJWT(['admin', 'student', 'recruiter', 'institute']);

    // Validate course_id from URL: /api/v1/courses/{id}/feedback
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            "status" => false,
            "message" => "Invalid or missing course ID"
        ]);
        exit();
    }
    $course_id = intval($_GET['id']);

    // Build SQL with role-based visibility
    if ($user_role === 'admin') {
        // Admin sees all feedback
        $sql = "SELECT * FROM course_feedback WHERE course_id = ?";
    } else {
        // Others see only approved feedback
        $sql = "SELECT * FROM course_feedback WHERE course_id = ? AND admin_action = 'approved'";
    }

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $course_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $feedbacks = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $feedbacks[] = $row;
        }

        http_response_code(200);
        echo json_encode([
            "status" => true,
            "data" => $feedbacks
        ]);

        mysqli_stmt_close($stmt);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => false,
            "message" => "Failed to fetch feedback: " . mysqli_error($conn)
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only POST and GET methods are allowed"
    ]);
}

mysqli_close($conn);
?>
