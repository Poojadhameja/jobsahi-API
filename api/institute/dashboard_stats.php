<?php
// institute_dashboard_stats.php
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

// ✅ Authenticate JWT (allow institute and admin)
$decoded   = authenticateJWT(['institute','admin']);
$user_id   = intval($decoded['user_id'] ?? 0);
$user_role = strtolower($decoded['role'] ?? '');

if ($user_id <= 0 || !in_array($user_role, ['institute', 'admin'])) {
    echo json_encode([
        "status"  => false,
        "message" => "Unauthorized access"
    ]);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // ------------------------------------------------------------------
    // 1️⃣ Get institute_id based on role
    // ------------------------------------------------------------------
    $institute_id = 0;

    if ($user_role === 'admin') {
        // Admin impersonation: Get institute_id from request parameters
        // Check multiple possible parameter names
        $provided_id = 0;
        if (isset($_GET['institute_id']) && !empty($_GET['institute_id'])) {
            $provided_id = intval($_GET['institute_id']);
        } elseif (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
            $provided_id = intval($_GET['user_id']);
        } elseif (isset($_GET['uid']) && !empty($_GET['uid'])) {
            $provided_id = intval($_GET['uid']);
        } elseif (isset($_GET['instituteId']) && !empty($_GET['instituteId'])) {
            $provided_id = intval($_GET['instituteId']);
        }

        if ($provided_id <= 0) {
            echo json_encode([
                "status"  => false,
                "message" => "Institute ID required for admin access. Please provide institute_id parameter."
            ]);
            exit;
        }

        // Try to find institute_id - first check if it's already an institute_profiles.id
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $provided_id);
        $stmt->execute();
        $inst = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($inst) {
            // Found by institute_profiles.id
            $institute_id = intval($inst['id']);
        } else {
            // Not found by id, try to find by user_id (convert user_id to institute_id)
            $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
            $stmt->bind_param("i", $provided_id);
            $stmt->execute();
            $inst = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($inst) {
                // Found by user_id
                $institute_id = intval($inst['id']);
            } else {
                echo json_encode([
                    "status"  => false,
                    "message" => "Institute not found. Please check if the institute profile exists."
                ]);
                exit;
            }
        }
    } else {
        // Institute role: Get institute_id from logged-in user
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
    }

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
    // 🟩 Placement Rate
    // ------------------------------------------------------------------

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

    $placement_rate = ($total_applications > 0)
        ? round(($selected_count / $total_applications) * 100, 1)
        : 0;

    // ------------------------------------------------------------------
    // 3️⃣ Recent Activities
    // ------------------------------------------------------------------

    $recent_activities = [];
    $activity_keys = []; // to remove duplicates

    // Enrollment
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
        $key = md5("enroll-" . $row['student_name'] . $row['course_title']);
        if (!isset($activity_keys[$key])) {
            $activity_keys[$key] = true;
            $recent_activities[] = [
                "title"     => "New student {$row['student_name']} enrolled in {$row['course_title']}",
                "timestamp" => $row['created_at'],
                "type"      => "enrollment"
            ];
        }
    }
    $q1->close();

    // Certificate
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
        $key = md5("cert-" . $row['student_name'] . $row['course_title']);
        if (!isset($activity_keys[$key])) {
            $activity_keys[$key] = true;
            $recent_activities[] = [
                "title"     => "Certificate generated for {$row['student_name']} in {$row['course_title']}",
                "timestamp" => $row['created_at'],
                "type"      => "certificate"
            ];
        }
    }
    $q2->close();

    // Updates
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
        $key = md5("update-" . $row['title']);
        if (!isset($activity_keys[$key])) {
            $activity_keys[$key] = true;
            $recent_activities[] = [
                "title"     => "{$row['title']} course updated",
                "timestamp" => $row['updated_at'],
                "type"      => "update"
            ];
        }
    }
    $q3->close();

    usort($recent_activities, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
    $recent_activities = array_slice($recent_activities, 0, 6);

    // ------------------------------------------------------------------
    // 4️⃣ Course Statistics (remove duplicate course names)
    // ------------------------------------------------------------------

    $course_statistics = [];
    $course_keys = [];

    $stmt4 = $conn->prepare("
        SELECT 
            c.id,
            c.title AS course_title,
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
        WHERE c.institute_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ");
    $stmt4->bind_param("i", $institute_id);
    $stmt4->execute();
    $res4 = $stmt4->get_result();
    while ($row = $res4->fetch_assoc()) {
        $key = md5($row['course_title']);
        if (!isset($course_keys[$key])) {
            $course_keys[$key] = true;
            $course_statistics[] = [
                "course_title"    => $row['course_title'],
                "completion_rate" => round($row['completion_rate'] ?? 0, 1)
            ];
        }
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
