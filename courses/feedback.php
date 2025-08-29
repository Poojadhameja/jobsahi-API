<?php
// courses_feedback.php - Submit course feedback
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config.php';  // DB connection

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed"
    ]);
    exit();
}

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

// Check if table and columns exist before insert
$check_sql = "SHOW COLUMNS FROM course_feedback LIKE 'feedback'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) == 0) {
    // Column doesn't exist, check for alternative column names
    $alt_check_sql = "SHOW COLUMNS FROM course_feedback";
    $alt_result = mysqli_query($conn, $alt_check_sql);
    
    $columns = [];
    while ($row = mysqli_fetch_assoc($alt_result)) {
        $columns[] = $row['Field'];
    }
    
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Column 'feedback' does not exist. Available columns: " . implode(', ', $columns)
    ]);
    exit();
}

// Insert into course_feedback
$sql = "INSERT INTO course_feedback (course_id, student_id, rating, feedback, created_at) 
        VALUES (?, ?, ?, ?, NOW())";

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

mysqli_close($conn);

?>