<?php
// course_by_batch.php — Unified for both UIs (Overview + Details + Faculty)
require_once '../cors.php';
require_once '../db.php';

try {
    // ============================================
    // 🔐 AUTHENTICATION
    // ============================================
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // Auto-detect institute_id
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));
    $institute_id = 0;

    if ($role === 'institute') {
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();
    }

    if ($role === 'institute' && $institute_id <= 0) {
        echo json_encode(["status" => false, "message" => "Institute ID missing or invalid in token"]);
        exit;
    }

    // Identify OVERVIEW or DETAIL mode
    $course_id = isset($_GET['id']) ? intval($_GET['id'])
                : (isset($_GET['course_id']) ? intval($_GET['course_id'])
                : 0);

    $current_date = new DateTime();


    // =====================================================================
    // 1️⃣ OVERVIEW PAGE
    // =====================================================================
    if ($course_id === 0) {

        $courseQuery = "
            SELECT id AS course_id, title AS course_title, instructor_name, duration, fee, admin_action
            FROM courses
            WHERE admin_action = 'approved'
            " . ($role === 'institute' ? "AND institute_id = ?" : "") . "
            ORDER BY created_at DESC
        ";

        $courseStmt = $conn->prepare($courseQuery);
        if ($role === 'institute') $courseStmt->bind_param("i", $institute_id);
        $courseStmt->execute();
        $courses = $courseStmt->get_result();

        $overviewData = [];

        while ($course = $courses->fetch_assoc()) {
            $cid = intval($course['course_id']);

            $batchQuery = "
                SELECT 
                    b.id,
                    b.start_date,
                    b.end_date,
                    b.admin_action,
                    COUNT(DISTINCT sb.student_id) AS total_students
                FROM batches b
                LEFT JOIN student_batches sb ON b.id = sb.batch_id
                WHERE b.course_id = ?
                GROUP BY b.id
                ORDER BY b.start_date DESC
            ";

            $batchStmt = $conn->prepare($batchQuery);
            $batchStmt->bind_param("i", $cid);
            $batchStmt->execute();
            $batches = $batchStmt->get_result();

            $total_batches = 0;
            $active_batches = 0;
            $progress_sum = 0;
            $progress_count = 0;
            $total_students = 0;

            while ($b = $batches->fetch_assoc()) {
                $total_batches++;
                $total_students += intval($b['total_students']);

                if (strtolower($b['admin_action']) === 'approved') {
                    $active_batches++;
                }

                $prog = 0;
                if (!empty($b['start_date']) && !empty($b['end_date'])) {
                    $start = new DateTime($b['start_date']);
                    $end = new DateTime($b['end_date']);

                    if ($current_date < $start) $prog = 0;
                    elseif ($current_date >= $end) $prog = 100;
                    else {
                        $days = $start->diff($end)->days;
                        $elapsed = $start->diff($current_date)->days;
                        $prog = ($days > 0) ? round(($elapsed / $days) * 100, 2) : 0;
                    }
                }

                $progress_sum += $prog;
                $progress_count++;
            }

            $overall_progress = ($progress_count > 0)
                ? round($progress_sum / $progress_count, 2)
                : 0;

            $overviewData[] = [
                "course_id"        => $course['course_id'],
                "course_title"     => $course['course_title'],
                "instructor_name"  => $course['instructor_name'],
                "fee"              => floatval($course['fee']),
                "total_batches"    => $total_batches,
                "active_batches"   => $active_batches,
                "overall_progress" => $overall_progress,
                "total_students"   => $total_students,
                "admin_action"     => $course['admin_action']
            ];
        }

        echo json_encode([
            "status"  => true,
            "message" => "Courses with batch progress fetched successfully.",
            "role"    => $role,
            "count"   => count($overviewData),
            "courses" => $overviewData
        ], JSON_PRETTY_PRINT);
        exit;
    }


    // =====================================================================
    // 2️⃣ DETAIL PAGE
    // =====================================================================
    $courseQuery = "
        SELECT id AS course_id, title AS course_title, instructor_name,
               duration, description, fee, admin_action
        FROM courses
        WHERE id = ? AND admin_action = 'approved'
        " . ($role === 'institute' ? "AND institute_id = ?" : "") . "
        LIMIT 1
    ";

    if ($role === 'institute') {
        $stmt = $conn->prepare($courseQuery);
        $stmt->bind_param("ii", $course_id, $institute_id);
    } else {
        $stmt = $conn->prepare($courseQuery);
        $stmt->bind_param("i", $course_id);
    }

    $stmt->execute();
    $courseResult = $stmt->get_result();

    if ($courseResult->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Course not found"]);
        exit;
    }

    $course = $courseResult->fetch_assoc();


    // ---------------------------------------------------
    // FETCH BATCHES
    // ---------------------------------------------------
    $batchQuery = "
        SELECT 
            b.id AS batch_id, 
            b.name AS batch_name, 
            b.batch_time_slot,
            b.start_date, 
            b.end_date, 
            b.admin_action,
            b.instructor_id,
            COUNT(DISTINCT sb.student_id) AS enrolled_students
        FROM batches b
        LEFT JOIN student_batches sb ON b.id = sb.batch_id
        WHERE b.course_id = ?
        GROUP BY b.id
        ORDER BY b.start_date DESC
    ";

    $batchStmt = $conn->prepare($batchQuery);
    $batchStmt->bind_param("i", $course_id);
    $batchStmt->execute();
    $batches = $batchStmt->get_result();

    $batchData = [];
    $active_batches = 0;

    while ($batch = $batches->fetch_assoc()) {

        $batch_id = intval($batch['batch_id']);
        $instructor_id = intval($batch['instructor_id']);

        if (strtolower($batch['admin_action']) === 'approved') {
            $active_batches++;
        }

        // ---------------------------------------------------------
        // FIX: join_date never NULL — fallback = batch.start_date
        // ---------------------------------------------------------
        $studentQuery = "
            SELECT DISTINCT
                sp.id AS student_id,
                u.user_name AS name,
                u.email,
                u.phone_number,
                COALESCE(
                    MIN(e.enrollment_date),
                    b.start_date
                ) AS enrollment_date,
                MAX(e.status) AS enrollment_status
            FROM student_batches sb
            INNER JOIN batches b ON sb.batch_id = b.id
            INNER JOIN student_profiles sp ON sb.student_id = sp.id
            INNER JOIN users u ON sp.user_id = u.id
            LEFT JOIN student_course_enrollments e 
                   ON e.student_id = sp.id AND e.course_id = ?
            WHERE sb.batch_id = ?
            GROUP BY sp.id, u.user_name, u.email, u.phone_number, b.start_date
            ORDER BY u.user_name ASC
        ";

        $sstmt = $conn->prepare($studentQuery);
        $sstmt->bind_param("ii", $course_id, $batch_id);
        $sstmt->execute();
        $studentResult = $sstmt->get_result();

        $students = [];
        while ($s = $studentResult->fetch_assoc()) {
            $students[] = [
                "student_id" => intval($s['student_id']),
                "name"       => $s['name'],
                "email"      => $s['email'],
                "join_date"  => $s['enrollment_date'], // NEVER NULL NOW
                "status"     => ucfirst($s['enrollment_status'] ?? 'Active')
            ];
        }

       // ---------------------------------------------------------
// FACULTY SECTION
// ---------------------------------------------------------
$faculty = [];

if ($instructor_id > 0) {
    // Fetch only assigned instructor
    $facultyQuery = "
        SELECT 
            fu.id AS faculty_id,
            fu.name,
            fu.email,
            fu.phone,
            fu.role
        FROM faculty_users fu
        WHERE fu.id = ?
          AND fu.admin_action = 'approved'
        LIMIT 1
    ";

    $fstmt = $conn->prepare($facultyQuery);
    $fstmt->bind_param("i", $instructor_id);
    $fstmt->execute();
    $facultyResult = $fstmt->get_result();

    while ($f = $facultyResult->fetch_assoc()) {
        $faculty[] = [
            "faculty_id" => intval($f['faculty_id']),
            "name"       => $f['name'],
            "email"      => $f['email'],
            "phone"      => $f['phone'],
            "role"       => ucfirst($f['role'])
        ];
    }
} else {
    // Fetch ALL faculty of the institute (for assignment)
    $facultyQuery = "
        SELECT 
            id AS faculty_id,
            name,
            email,
            phone,
            role
        FROM faculty_users
        WHERE institute_id = ?
          AND admin_action = 'approved'
        ORDER BY name ASC
    ";

    $fstmt = $conn->prepare($facultyQuery);
    $fstmt->bind_param("i", $institute_id);
    $fstmt->execute();
    $facultyResult = $fstmt->get_result();

    while ($f = $facultyResult->fetch_assoc()) {
        $faculty[] = [
            "faculty_id" => intval($f['faculty_id']),
            "name"       => $f['name'],
            "email"      => $f['email'],
            "phone"      => $f['phone'],
            "role"       => ucfirst($f['role'])
        ];
    }
}


        // Final batch array
        $batchData[] = [
            "batch_id"          => $batch['batch_id'],
            "batch_name"        => $batch['batch_name'],
            "batch_time_slot"   => $batch['batch_time_slot'],
            "start_date"        => $batch['start_date'],
            "end_date"          => $batch['end_date'],
            "status"            => ucfirst($batch['admin_action']),
            "enrolled_students" => intval($batch['enrolled_students']),
            "students"          => $students,
            "faculty"           => $faculty
        ];
    }

    echo json_encode([
        "status"  => true,
        "message" => "Course details with batches, enrolled students, and assigned faculty fetched successfully.",
        "role"    => $role,
        "course"  => $course,
        "batches" => $batchData,
        "active_batches" => $active_batches
    ], JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
