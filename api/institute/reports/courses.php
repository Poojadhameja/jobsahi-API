<?php
// courses.php - Course analytics report (JWT protected)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include __DIR__ . "/../../db.php";
require_once __DIR__ . "/../../jwt_token/jwt_helper.php";
require_once __DIR__ . "/../../auth/auth_middleware.php";

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'institute']); 
$role = $decoded['role']; // Assuming 'role' exists in your JWT payload

try {
    // ✅ Apply role-based filter for admin_action
    if ($role === 'admin') {
        // Admin sees both pending and approval
        $sql = "
            SELECT 
                e.course_id,
                COUNT(DISTINCT e.student_id) AS total_enrollments,
                COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.student_id END) AS completed_enrollments,
                COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.student_id END) AS active_enrollments
            FROM student_course_enrollments e
            WHERE (e.deleted_at IS NULL OR e.deleted_at = '')
              AND (e.admin_action = 'pending' OR e.admin_action = 'approval')
            GROUP BY e.course_id
            ORDER BY total_enrollments DESC
        ";
    } else {
        // Recruiter, Institute, Students → only approval
        $sql = "
            SELECT 
                e.course_id,
                COUNT(DISTINCT e.student_id) AS total_enrollments,
                COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.student_id END) AS completed_enrollments,
                COUNT(DISTINCT CASE WHEN e.status = 'enrolled' THEN e.student_id END) AS active_enrollments
            FROM student_course_enrollments e
            WHERE (e.deleted_at IS NULL OR e.deleted_at = '')
              AND e.admin_action = 'approval'
            GROUP BY e.course_id
            ORDER BY total_enrollments DESC
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $courses_analytics = [];
    while ($row = $result->fetch_assoc()) {
        $courses_analytics[] = [
            'course_id' => $row['course_id'],
            'enrollments' => [
                'total' => (int)$row['total_enrollments'],
                'completed' => (int)$row['completed_enrollments'],
                'active' => (int)$row['active_enrollments']
            ]
        ];
    }

    // ✅ Summary
    $total_courses = count($courses_analytics);
    $total_enrollments = array_sum(array_column(array_column($courses_analytics, 'enrollments'), 'total'));
    $total_completed = array_sum(array_column(array_column($courses_analytics, 'enrollments'), 'completed'));

    echo json_encode([
        "status" => true,
        "message" => "Course analytics report retrieved successfully",
        "data" => [
            "summary" => [
                "total_courses" => $total_courses,
                "total_enrollments" => $total_enrollments,
                "total_completed" => $total_completed,
                "completion_rate" => $total_enrollments > 0 ? round(($total_completed / $total_enrollments) * 100, 2) : 0
            ],
            "courses" => $courses_analytics
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error retrieving course analytics: " . $e->getMessage()
    ]);
}

$conn->close();
?>
