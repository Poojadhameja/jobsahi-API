<?php
// course_analytics.php - Course and enrollment analytics (JWT protected) - Grouped by Course Name
require_once(__DIR__ . '/../../cors.php');

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'institute']); 
$role = $decoded['role']; // Assuming 'role' exists in your JWT payload

try {
    // ✅ Get course analytics grouped by course name with enrollment counts
    $sql = "
        SELECT 
            c.title as course_name,
            COUNT(DISTINCT sce.id) as total_enrollments,
            COUNT(DISTINCT CASE WHEN sce.status = 'completed' THEN sce.id END) as completed_enrollments,
            COUNT(DISTINCT CASE WHEN sce.status = 'enrolled' THEN sce.id END) as active_enrollments,
            COUNT(DISTINCT CASE WHEN sce.status = 'in_progress' THEN sce.id END) as in_progress_enrollments,
            COUNT(DISTINCT CASE WHEN sce.status NOT IN ('completed', 'enrolled', 'in_progress') THEN sce.id END) as not_started_enrollments
        FROM courses c
        LEFT JOIN student_course_enrollments sce ON c.id = sce.course_id
        GROUP BY c.title
        ORDER BY total_enrollments DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $course_analytics = [];
    $total_enrollments_all = 0;
    $total_completed_all = 0;
    $total_active_all = 0;
    $total_in_progress_all = 0;
    $total_not_started_all = 0;

    while ($row = $result->fetch_assoc()) {
        $completion_rate = 0;
        $in_progress_rate = 0;
        $not_started_rate = 0;
        
        if ($row['total_enrollments'] > 0) {
            $completion_rate = round(($row['completed_enrollments'] / $row['total_enrollments']) * 100, 2);
            $in_progress_rate = round(($row['in_progress_enrollments'] / $row['total_enrollments']) * 100, 2);
            $not_started_rate = round(($row['not_started_enrollments'] / $row['total_enrollments']) * 100, 2);
        }
        
        $course_analytics[] = [
            'course_name' => $row['course_name'],
            'total_enrollments' => (int)$row['total_enrollments'],
            // 'completed_enrollments' => (int)$row['completed_enrollments']
        ];

        // Add to totals
        $total_enrollments_all += (int)$row['total_enrollments'];
        $total_completed_all += (int)$row['completed_enrollments'];
    }

    // ✅ Overall Summary
    $total_courses = count($course_analytics);
    $overall_completion_rate = $total_enrollments_all > 0 ? round(($total_completed_all / $total_enrollments_all) * 100, 2) : 0;
    $overall_in_progress_rate = $total_enrollments_all > 0 ? round(($total_in_progress_all / $total_enrollments_all) * 100, 2) : 0;
    $overall_not_started_rate = $total_enrollments_all > 0 ? round(($total_not_started_all / $total_enrollments_all) * 100, 2) : 0;

    // ✅ Get unique student count from student_course_enrollments
    $student_count_sql = "SELECT COUNT(DISTINCT student_id) as unique_students FROM student_course_enrollments";
    $student_stmt = $conn->prepare($student_count_sql);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    $unique_students = $student_result->fetch_assoc()['unique_students'];

    // ✅ Course popularity data for pie chart (like in your image)
    $course_popularity = [];
    foreach ($course_analytics as $course) {
        $course_popularity[] = [
            'course_name' => $course['course_name'],
            'enrollment_count' => $course['total_enrollments']
        ];
    }

    echo json_encode([
        "status" => true,
        "message" => "Course analytics retrieved successfully",
        "data" => [
            "course_analytics" => $course_analytics
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