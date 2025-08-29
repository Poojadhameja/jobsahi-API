<?php
// get_courses_feedback.php - Fetch feedback for a course
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../config.php';  // DB connection

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "status" => false,
        "message" => "Only GET method is allowed"
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

// Optional query parameters for pagination and filtering
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 10;
$rating_filter = isset($_GET['rating']) && is_numeric($_GET['rating']) ? intval($_GET['rating']) : null;
$order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], ['created_at', 'rating']) ? $_GET['order_by'] : 'created_at';
$order_dir = isset($_GET['order_dir']) && strtoupper($_GET['order_dir']) === 'ASC' ? 'ASC' : 'DESC';

$offset = ($page - 1) * $limit;

// Build the WHERE clause
$where_conditions = ["course_id = ?"];
$params = [$course_id];
$param_types = "i";

if ($rating_filter !== null && $rating_filter >= 1 && $rating_filter <= 5) {
    $where_conditions[] = "rating = ?";
    $params[] = $rating_filter;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// First, check if the course exists
$course_check_sql = "SELECT id FROM courses WHERE id = ?";
if ($course_stmt = mysqli_prepare($conn, $course_check_sql)) {
    mysqli_stmt_bind_param($course_stmt, "i", $course_id);
    mysqli_stmt_execute($course_stmt);
    $course_result = mysqli_stmt_get_result($course_stmt);
    
    if (mysqli_num_rows($course_result) == 0) {
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => "Course not found"
        ]);
        mysqli_stmt_close($course_stmt);
        mysqli_close($conn);
        exit();
    }
    mysqli_stmt_close($course_stmt);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM course_feedback WHERE $where_clause";
if ($count_stmt = mysqli_prepare($conn, $count_sql)) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_feedback = mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Failed to prepare count statement: " . mysqli_error($conn)
    ]);
    exit();
}

// Get feedback data from course_feedback table (matching your schema)
$sql = "SELECT 
            id,
            student_id,
            rating,
            feedback,
            created_at
        FROM course_feedback
        WHERE $where_clause
        ORDER BY $order_by $order_dir
        LIMIT ? OFFSET ?";

// Add limit and offset to parameters
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $feedback_list = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $feedback_list[] = [
                "id" => intval($row['id']),
                "student_id" => intval($row['student_id']),
                "rating" => intval($row['rating']),
                "feedback" => $row['feedback'],  // Using 'feedback' column as per your schema
                "created_at" => $row['created_at']
            ];
        }
        
        // Calculate pagination info
        $total_pages = ceil($total_feedback / $limit);
        
        // Calculate average rating
        $avg_sql = "SELECT AVG(rating) as avg_rating FROM course_feedback WHERE course_id = ?";
        if ($avg_stmt = mysqli_prepare($conn, $avg_sql)) {
            mysqli_stmt_bind_param($avg_stmt, "i", $course_id);
            mysqli_stmt_execute($avg_stmt);
            $avg_result = mysqli_stmt_get_result($avg_stmt);
            $avg_rating = mysqli_fetch_assoc($avg_result)['avg_rating'];
            mysqli_stmt_close($avg_stmt);
        }
        
        // Calculate rating distribution
        $dist_sql = "SELECT rating, COUNT(*) as count FROM course_feedback WHERE course_id = ? GROUP BY rating ORDER BY rating DESC";
        $rating_distribution = [];
        if ($dist_stmt = mysqli_prepare($conn, $dist_sql)) {
            mysqli_stmt_bind_param($dist_stmt, "i", $course_id);
            mysqli_stmt_execute($dist_stmt);
            $dist_result = mysqli_stmt_get_result($dist_stmt);
            
            while ($row = mysqli_fetch_assoc($dist_result)) {
                $rating_distribution[intval($row['rating'])] = intval($row['count']);
            }
            mysqli_stmt_close($dist_stmt);
        }
        
        http_response_code(200);
        echo json_encode([
            "status" => true,
            "data" => [
                "course_id" => $course_id,
                "feedback" => $feedback_list,
                "summary" => [
                    "total_feedback" => intval($total_feedback),
                    "average_rating" => $avg_rating ? round(floatval($avg_rating), 2) : null,
                    "rating_distribution" => $rating_distribution
                ],
                "pagination" => [
                    "current_page" => $page,
                    "total_pages" => $total_pages,
                    "total_items" => intval($total_feedback),
                    "items_per_page" => $limit,
                    "has_next_page" => $page < $total_pages,
                    "has_previous_page" => $page > 1
                ]
            ]
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