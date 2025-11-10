<?php
// get_courses_feedback.php - Fetch all or specific course feedback (admin & student)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate roles
$user = authenticateJWT(['admin', 'student']);
$user_role = $user['role'] ?? null;

// ✅ Optional course ID
$course_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// ✅ Optional filters
$rating_filter = isset($_GET['rating']) && is_numeric($_GET['rating']) ? intval($_GET['rating']) : null;
$order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], ['created_at', 'rating']) ? $_GET['order_by'] : 'created_at';
$order_dir = isset($_GET['order_dir']) && strtoupper($_GET['order_dir']) === 'ASC' ? 'ASC' : 'DESC';

// ✅ Build base query
$where_conditions = [];
$params = [];
$param_types = "";

// Add course filter (if provided)
if ($course_id) {
    $where_conditions[] = "cf.course_id = ?";
    $params[] = $course_id;
    $param_types .= "i";
}

// Add rating filter
if ($rating_filter !== null && $rating_filter >= 1 && $rating_filter <= 5) {
    $where_conditions[] = "cf.rating = ?";
    $params[] = $rating_filter;
    $param_types .= "i";
}

// Role-based visibility
if ($user_role === 'admin') {
    $where_conditions[] = "(cf.admin_action = 'approved' OR cf.admin_action = 'pending')";
} else {
    $where_conditions[] = "cf.admin_action = 'approved'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ✅ Fetch feedback data (no limit or offset)
$sql = "
    SELECT 
        cf.id,
        cf.course_id,
        c.title AS course_name,
        u.user_name AS student_name,
        cf.rating,
        cf.feedback,
        cf.admin_action,
        cf.created_at
    FROM course_feedback cf
    JOIN student_profiles sp ON cf.student_id = sp.id
    JOIN users u ON sp.user_id = u.id
    JOIN courses c ON cf.course_id = c.id
    $where_clause
    ORDER BY cf.$order_by $order_dir
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ✅ Structure data
$feedback_data = [];
while ($row = $result->fetch_assoc()) {
    $feedback_data[] = [
        "feedback_id" => intval($row['id']),
        "course_id" => intval($row['course_id']),
        "course_name" => $row['course_name'],
        "student_name" => $row['student_name'],
        "rating" => intval($row['rating']),
        "feedback" => $row['feedback'],
        "admin_action" => $row['admin_action'],
        "created_at" => $row['created_at']
    ];
}
$stmt->close();

// ✅ If course_id is provided → show extra stats
if ($course_id) {
    // Average rating
    $avg_sql = "
        SELECT AVG(rating) AS avg_rating 
        FROM course_feedback 
        WHERE course_id = ? AND " . 
        ($user_role === 'admin' ? "(admin_action IN ('approved','pending'))" : "admin_action = 'approved'");
    $avg_stmt = $conn->prepare($avg_sql);
    $avg_stmt->bind_param("i", $course_id);
    $avg_stmt->execute();
    $avg_res = $avg_stmt->get_result();
    $avg_rating = round($avg_res->fetch_assoc()['avg_rating'], 2);
    $avg_stmt->close();

    // Rating distribution
    $dist_sql = "
        SELECT rating, COUNT(*) AS count 
        FROM course_feedback 
        WHERE course_id = ? AND " . 
        ($user_role === 'admin' ? "(admin_action IN ('approved','pending'))" : "admin_action = 'approved'") . " 
        GROUP BY rating ORDER BY rating DESC";
    $dist_stmt = $conn->prepare($dist_sql);
    $dist_stmt->bind_param("i", $course_id);
    $dist_stmt->execute();
    $dist_res = $dist_stmt->get_result();
    $rating_distribution = [];
    while ($r = $dist_res->fetch_assoc()) {
        $rating_distribution[intval($r['rating'])] = intval($r['count']);
    }
    $dist_stmt->close();
}

mysqli_close($conn);

// ✅ Final Response
$response = [
    "status" => true,
    "message" => $course_id ? "Course feedback fetched successfully" : "All feedback fetched successfully",
    "data" => $feedback_data
];

if ($course_id) {
    $response["stats"] = [
        "average_rating" => $avg_rating ?? 0,
        "rating_distribution" => $rating_distribution ?? []
    ];
}

http_response_code(200);
echo json_encode($response);
?>
