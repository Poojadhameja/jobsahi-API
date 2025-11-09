<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Only GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET method allowed"]);
    exit;
}

try {
    // ✅ Authenticate (Admin or Institute)
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role']);
    $user_id = intval($decoded['user_id']);

    // ✅ Get Institute ID
    $institute_id = 0;
    if ($role === 'institute') {
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? AND admin_action = 'approved' LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $institute_id = intval($result['id'] ?? 0);
    }

    if ($institute_id <= 0) {
        echo json_encode(["status" => false, "message" => "Institute not found or not approved"]);
        exit;
    }

    // ---------------------------------------------------
    // 1️⃣ Total Students (approved + enrolled)
    // ---------------------------------------------------
    $sql = "
        SELECT COUNT(DISTINCT sp.id) AS total_students
        FROM student_profiles sp
        JOIN student_course_enrollments sce ON sp.id = sce.student_id
        JOIN courses c ON c.id = sce.course_id
        WHERE c.institute_id = ? AND sp.admin_action = 'approved'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $total_students = intval($stmt->get_result()->fetch_assoc()['total_students'] ?? 0);

    // ---------------------------------------------------
    // 2️⃣ Course Completion Rate
    // ---------------------------------------------------
    $sql = "
        SELECT 
            SUM(CASE WHEN sce.status = 'completed' THEN 1 ELSE 0 END) AS completed,
            COUNT(*) AS total
        FROM student_course_enrollments sce
        JOIN courses c ON sce.course_id = c.id
        WHERE c.institute_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $completion_rate = ($stats['total'] > 0)
        ? round(($stats['completed'] / $stats['total']) * 100, 2)
        : 0;

    // ---------------------------------------------------
    // 3️⃣ Total Courses
    // ---------------------------------------------------
    $sql = "SELECT COUNT(*) AS total_courses FROM courses WHERE institute_id = ? AND admin_action = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $total_courses = intval($stmt->get_result()->fetch_assoc()['total_courses'] ?? 0);

    // ---------------------------------------------------
    // 4️⃣ Active Batches
    // ---------------------------------------------------
    $sql = "
        SELECT COUNT(*) AS total_batches
        FROM batches b
        JOIN courses c ON b.course_id = c.id
        WHERE c.institute_id = ? AND b.admin_action = 'approved'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $total_batches = intval($stmt->get_result()->fetch_assoc()['total_batches'] ?? 0);

    // ---------------------------------------------------
    // 5️⃣ Student Completion by Course (Bar Chart)
    // ---------------------------------------------------
    $sql = "
        SELECT 
            c.title AS course_name,
            SUM(CASE WHEN sce.status = 'completed' THEN 1 ELSE 0 END) AS completed_students
        FROM student_course_enrollments sce
        JOIN courses c ON sce.course_id = c.id
        WHERE c.institute_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $student_completion_chart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // ---------------------------------------------------
    // 6️⃣ Course Popularity (Pie Chart with Name)
    // ---------------------------------------------------
    $sql = "
        SELECT 
            c.title AS course_name,
            COUNT(sce.id) AS total_enrolled,
            SUM(CASE WHEN sce.status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN sce.status = 'enrolled' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN sce.status = 'dropped' THEN 1 ELSE 0 END) AS not_started
        FROM student_course_enrollments sce
        JOIN courses c ON sce.course_id = c.id
        WHERE c.institute_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $course_popularity_chart = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // ✅ Return data
    echo json_encode([
        "status" => true,
        "message" => "Institute report generated successfully",
        "data" => [
            "metrics" => [
                "total_students" => $total_students,
                "completion_rate" => $completion_rate,
                "total_courses" => $total_courses,
                "active_batches" => $total_batches
            ],
            "student_completion_chart" => $student_completion_chart,
            "course_popularity_chart" => $course_popularity_chart
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>
