<?php
// institute_dashboard_stats.php
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate JWT (only institute)
$decoded   = authenticateJWT(['institute']);
$user_id   = intval($decoded['user_id'] ?? 0);
$user_role = strtolower($decoded['role'] ?? '');

if ($user_id <= 0 || $user_role !== 'institute') {
    echo json_encode([
        "status"  => false,
        "message" => "Unauthorized access"
    ]);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // ------------------------------------------------------------------
    // 1️⃣ Get institute_id for this logged-in institute user
    // ------------------------------------------------------------------
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $inst = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inst) {
        echo json_encode([
            "status"  => false,
            "message" => "Institute profile not found for this user"
        ]);
        exit;
    }

    $institute_id = intval($inst['id']);

    // ------------------------------------------------------------------
    // 2️⃣ Summary Cards
    // ------------------------------------------------------------------

    // Total Courses
    $stmt1 = $conn->prepare("SELECT COUNT(*) FROM courses WHERE institute_id = ?");
    $stmt1->bind_param("i", $institute_id);
    $stmt1->execute();
    $stmt1->bind_result($total_courses);
    $stmt1->fetch();
    $stmt1->close();

    // Enrolled Students
    $stmt2 = $conn->prepare("
        SELECT COUNT(*) 
        FROM student_course_enrollments e
        INNER JOIN courses c ON e.course_id = c.id
        WHERE c.institute_id = ?
          AND e.status IN ('enrolled','completed')
          AND e.admin_action = 'approved'
          AND e.deleted_at IS NULL
    ");
    $stmt2->bind_param("i", $institute_id);
    $stmt2->execute();
    $stmt2->bind_result($enrolled_students);
    $stmt2->fetch();
    $stmt2->close();

    // Certified Students
    $stmt3 = $conn->prepare("
        SELECT COUNT(*) 
        FROM certificates cert
        INNER JOIN courses c ON cert.course_id = c.id
        WHERE c.institute_id = ?
          AND cert.admin_action = 'approved'
    ");
    $stmt3->bind_param("i", $institute_id);
    $stmt3->execute();
    $stmt3->bind_result($certified_students);
    $stmt3->fetch();
    $stmt3->close();

    // ------------------------------------------------------------------
    // 🟩 Placement Rate (Final Logic)
    // ------------------------------------------------------------------
    // Placement Rate = (Number of students selected in jobs / Total number of job applications) × 100

    // Total job applications for this institute's students
    $stmtA = $conn->prepare("
        SELECT COUNT(DISTINCT a.id)
        FROM applications a
        INNER JOIN student_profiles s ON a.student_id = s.id
        INNER JOIN student_course_enrollments e ON s.id = e.student_id
        INNER JOIN courses c ON e.course_id = c.id
        WHERE c.institute_id = ?
          AND a.admin_action = 'approved'
          AND a.deleted_at IS NULL
    ");
    $stmtA->bind_param("i", $institute_id);
    $stmtA->execute();
    $stmtA->bind_result($total_applications);
    $stmtA->fetch();
    $stmtA->close();

    // Students selected in jobs (status = 'selected' OR job_selected = 1)
    $stmtB = $conn->prepare("
        SELECT COUNT(DISTINCT a.id)
        FROM applications a
        INNER JOIN student_profiles s ON a.student_id = s.id
        INNER JOIN student_course_enrollments e ON s.id = e.student_id
        INNER JOIN courses c ON e.course_id = c.id
        WHERE c.institute_id = ?
          AND a.admin_action = 'approved'
          AND a.deleted_at IS NULL
          AND (a.status = 'selected' OR a.job_selected = 1)
    ");
    $stmtB->bind_param("i", $institute_id);
    $stmtB->execute();
    $stmtB->bind_result($selected_count);
    $stmtB->fetch();
    $stmtB->close();

    $placement_rate = 0;
    if ($total_applications > 0) {
        $placement_rate = round(($selected_count / $total_applications) * 100, 1);
    }

    // ------------------------------------------------------------------
    // 3️⃣ Recent Activities (unchanged)
    // ------------------------------------------------------------------
    $recent_activities = [];

    // Enrollments
    $q1 = $conn->prepare("
        SELECT u.user_name AS student_name, c.title AS course_title, e.created_at
        FROM student_course_enrollments e
        INNER JOIN student_profiles s ON e.student_id = s.id
        INNER JOIN users u ON s.user_id = u.id
        INNER JOIN courses c ON e.course_id = c.id
        WHERE c.institute_id = ?
          AND e.admin_action = 'approved'
          AND e.deleted_at IS NULL
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $q1->bind_param("i", $institute_id);
    $q1->execute();
    $res1 = $q1->get_result();
    while ($row = $res1->fetch_assoc()) {
        $recent_activities[] = [
            "title"     => "New student {$row['student_name']} enrolled in {$row['course_title']}",
            "timestamp" => $row['created_at'],
            "type"      => "enrollment"
        ];
    }
    $q1->close();

    // Certificates
    $q2 = $conn->prepare("
        SELECT u.user_name AS student_name, c.title AS course_title, cert.created_at
        FROM certificates cert
        INNER JOIN student_profiles s ON cert.student_id = s.id
        INNER JOIN users u ON s.user_id = u.id
        INNER JOIN courses c ON cert.course_id = c.id
        WHERE c.institute_id = ?
          AND cert.admin_action = 'approved'
        ORDER BY cert.created_at DESC
        LIMIT 5
    ");
    $q2->bind_param("i", $institute_id);
    $q2->execute();
    $res2 = $q2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $recent_activities[] = [
            "title"     => "Certificate generated for {$row['student_name']} in {$row['course_title']}",
            "timestamp" => $row['created_at'],
            "type"      => "certificate"
        ];
    }
    $q2->close();

    // Courses Updated
    $q3 = $conn->prepare("
        SELECT title, updated_at
        FROM courses
        WHERE institute_id = ?
        ORDER BY updated_at DESC
        LIMIT 3
    ");
    $q3->bind_param("i", $institute_id);
    $q3->execute();
    $res3 = $q3->get_result();
    while ($row = $res3->fetch_assoc()) {
        $recent_activities[] = [
            "title"     => "{$row['title']} course updated",
            "timestamp" => $row['updated_at'],
            "type"      => "update"
        ];
    }
    $q3->close();

    usort($recent_activities, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
    $recent_activities = array_slice($recent_activities, 0, 6);

    // ------------------------------------------------------------------
    // 4️⃣ Course Statistics Section (UPDATED - Daily Completion Logic)
    // ------------------------------------------------------------------
    $course_statistics = [];
    $stmt4 = $conn->prepare("
        SELECT 
            c.id,
            c.title AS course_title,
            COUNT(DISTINCT e.student_id) AS total_enrolled,
            COUNT(DISTINCT cert.student_id) AS total_certified,
            ROUND(
                AVG(
                    CASE
                        WHEN b.start_date IS NULL OR b.end_date IS NULL THEN 0
                        WHEN CURDATE() < b.start_date THEN 0
                        WHEN CURDATE() >= b.end_date THEN 100
                        ELSE ROUND(
                            (DATEDIFF(CURDATE(), b.start_date) / NULLIF(DATEDIFF(b.end_date, b.start_date), 0)) * 100, 
                            1
                        )
                    END
                ), 1
            ) AS completion_rate
        FROM courses c
        LEFT JOIN batches b ON b.course_id = c.id
        LEFT JOIN student_course_enrollments e ON e.course_id = c.id
        LEFT JOIN certificates cert ON cert.course_id = c.id
        WHERE c.institute_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ");
    $stmt4->bind_param("i", $institute_id);
    $stmt4->execute();
    $res4 = $stmt4->get_result();
    while ($row = $res4->fetch_assoc()) {
        $course_statistics[] = [
            "course_title"    => $row['course_title'],
            "completion_rate" => round($row['completion_rate'] ?? 0, 1)
        ];
    }
    $stmt4->close();

    // ------------------------------------------------------------------
    // 5️⃣ Final JSON Response
    // ------------------------------------------------------------------
    echo json_encode([
        "status"  => true,
        "message" => "Institute dashboard data fetched successfully",
        "data"    => [
            "summary" => [
                "total_courses"      => (int) $total_courses,
                "enrolled_students"  => (int) $enrolled_students,
                "certified_students" => (int) $certified_students,
                "placement_rate"     => $placement_rate
            ],
            "recent_activities" => $recent_activities,
            "course_statistics" => $course_statistics
        ]
    ], JSON_PRETTY_PRINT);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "SQL Error: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
