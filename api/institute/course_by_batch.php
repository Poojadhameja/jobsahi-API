<?php
// course_by_batch.php — Unified for both UIs (Overview + Details + Faculty)
require_once '../cors.php';
require_once '../db.php';

try {
    // ==========================================
    // 🔐 AUTHENTICATION
    // ==========================================
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // Get institute_id for institute role
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));
    $institute_id = 0;

    if ($role === 'institute') {
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();
    }

    if ($role === 'institute' && $institute_id <= 0) {
        echo json_encode(["status" => false, "message" => "Institute ID missing or invalid in token"]);
        exit;
    }

    // Detect Overview or Details view
    $course_id = isset($_GET['id']) ? intval($_GET['id']) :
                (isset($_GET['course_id']) ? intval($_GET['course_id']) : 0);

    $current_date = new DateTime();

    // ======================================================
    // 1️⃣ OVERVIEW PAGE (ALL COURSES + BATCH SUMMARY)
    // ======================================================
    if ($course_id === 0) {

        $courseQuery = "
            SELECT id AS course_id, title AS course_title, instructor_name, duration, fee, admin_action, certification_allowed
            FROM courses
            WHERE admin_action = 'approved'
            ".($role === 'institute' ? " AND institute_id = ?" : "")."
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

            while ($batch = $batches->fetch_assoc()) {
                $total_batches++;
                $total_students += intval($batch['total_students']);

                if (strtolower($batch['admin_action']) === 'approved') $active_batches++;

                $progress = 0;
                if (!empty($batch['start_date']) && !empty($batch['end_date'])) {
                    $start = new DateTime($batch['start_date']);
                    $end = new DateTime($batch['end_date']);
                    if ($current_date < $start) $progress = 0;
                    elseif ($current_date >= $end) $progress = 100;
                    else {
                        $days = $start->diff($end)->days;
                        $elapsed = $start->diff($current_date)->days;
                        $progress = ($days > 0) ? round(($elapsed / $days) * 100, 2) : 0;
                    }
                }

                $progress_sum += $progress;
                $progress_count++;
            }

            $overall_progress = ($progress_count > 0)
                ? round(($progress_sum / $progress_count), 2)
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
                "admin_action"     => $course['admin_action'],
                "certification_allowed" => (intval($course['certification_allowed']) === 1) ? "yes" : "no"
            ];
        }

        echo json_encode([
            "status" => true,
            "message" => "Courses with batch progress fetched successfully.",
            "role" => $role,
            "count" => count($overviewData),
            "courses" => $overviewData
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ======================================================
    // 2️⃣ DETAILS PAGE (SINGLE COURSE + BATCHES + STUDENTS + FACULTY)
    // ======================================================
    else {

        $courseQuery = "
            SELECT id AS course_id, title AS course_title, instructor_name,
                   duration, description, fee, admin_action, certification_allowed
            FROM courses
            WHERE id = ? AND admin_action = 'approved'
            ".($role === 'institute' ? " AND institute_id = ?" : "")."
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
        $courseRes = $stmt->get_result();

        if ($courseRes->num_rows === 0) {
            echo json_encode(["status" => false, "message" => "Course not found"]);
            exit;
        }

        $course = $courseRes->fetch_assoc();
        
        // Convert certification_allowed to yes/no
        $course['certification_allowed'] = (intval($course['certification_allowed']) === 1) ? "yes" : "no";

        // ================================
        // FETCH BATCHES (WITH STUDENTS)
        // ================================
        $batchQuery = "
            SELECT 
                b.id AS batch_id,
                b.name AS batch_name,
                b.batch_time_slot,
                b.start_date,
                b.end_date,
                b.instructor_id,
                b.admin_action,
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
            $inst_id  = intval($batch['instructor_id']);

            if (strtolower($batch['admin_action']) === 'approved') {
                $active_batches++;
            }

            // ===============================
            // 🎯 FETCH ASSIGNED INSTRUCTOR
            // ===============================
            $assigned_instructor = null;

            if ($inst_id > 0) {
                $instQ = "
                    SELECT id AS faculty_id, name, email, phone
                    FROM faculty_users
                    WHERE id = ? LIMIT 1
                ";
                $instStmt = $conn->prepare($instQ);
                $instStmt->bind_param("i", $inst_id);
                $instStmt->execute();
                $instRes = $instStmt->get_result();

                if ($inst = $instRes->fetch_assoc()) {
                    $assigned_instructor = [
                        "faculty_id" => intval($inst['faculty_id']),
                        "name"       => $inst['name'],
                        "email"      => $inst['email'],
                        "phone"      => $inst['phone']
                    ];
                }
            }

            // ===============================
            // FETCH STUDENTS FOR THIS BATCH
            // ===============================
            $stuQuery = "
                SELECT DISTINCT
                    sp.id AS student_id,
                    u.user_name AS name,
                    u.email,
                    u.phone_number,
                    MIN(e.enrollment_date) AS enrollment_date,
                    MAX(e.status) AS enrollment_status
                FROM student_batches sb
                INNER JOIN student_profiles sp ON sb.student_id = sp.id
                INNER JOIN users u ON sp.user_id = u.id
                LEFT JOIN student_course_enrollments e 
                       ON e.student_id = sp.id AND e.course_id = ?
                WHERE sb.batch_id = ?
                GROUP BY sp.id
                ORDER BY u.user_name ASC
            ";

            $stuStmt = $conn->prepare($stuQuery);
            $stuStmt->bind_param("ii", $course_id, $batch_id);
            $stuStmt->execute();
            $stuRes = $stuStmt->get_result();

            $students = [];
            while ($st = $stuRes->fetch_assoc()) {
                $students[] = [
                    "student_id" => intval($st['student_id']),
                    "name"       => $st['name'],
                    "email"      => $st['email'],
                    "join_date"  => $st['enrollment_date'],
                    "status"     => ucfirst($st['enrollment_status'] ?? 'Active')
                ];
            }

            // ===============================
            // FINAL BATCH DATA
            // ===============================
            $batchData[] = [
                "batch_id"          => $batch['batch_id'],
                "batch_name"        => $batch['batch_name'],
                "batch_time_slot"   => $batch['batch_time_slot'],
                "start_date"        => $batch['start_date'],
                "end_date"          => $batch['end_date'],
                "status"            => ucfirst($batch['admin_action']),
                "enrolled_students" => intval($batch['enrolled_students']),
                "assigned_instructor" => $assigned_instructor,
                "students"          => $students
            ];
        }

        echo json_encode([
            "status" => true,
            "message" => "Course details with batches, students, and faculty fetched successfully.",
            "role" => $role,
            "course" => $course,
            "batches" => $batchData,
            "active_batches" => $active_batches
        ], JSON_PRETTY_PRINT);

        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
