<?php
// course_by_batch.php — Unified for both UIs (Overview + Details + Faculty)
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // ✅ Auto-detect institute_id from token
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

    // Determine overview or detail
    $course_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['course_id']) ? intval($_GET['course_id']) : 0);
    $current_date = new DateTime();

    // ----------------------------------------------------------
    // 1️⃣ OVERVIEW PAGE: All Courses + Batch Stats
    // ----------------------------------------------------------
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
            $course_id = intval($course['course_id']);

            $batchQuery = "SELECT id, start_date, end_date, admin_action FROM batches WHERE course_id = ?";
            $batchStmt = $conn->prepare($batchQuery);
            $batchStmt->bind_param("i", $course_id);
            $batchStmt->execute();
            $batches = $batchStmt->get_result();

            $total_batches = 0;
            $active_batches = 0;
            $progress_sum = 0;
            $progress_count = 0;

            while ($batch = $batches->fetch_assoc()) {
                $total_batches++;
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

            $overall_progress = ($progress_count > 0) ? round($progress_sum / $progress_count, 2) : 0;

            $overviewData[] = [
                "course_id"        => $course['course_id'],
                "course_title"     => $course['course_title'],
                "instructor_name"  => $course['instructor_name'],
                "fee"              => floatval($course['fee']),
                "total_batches"    => $total_batches,
                "active_batches"   => $active_batches,
                "overall_progress" => $overall_progress,
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

    // ----------------------------------------------------------
    // 2️⃣ DETAILS PAGE: Course Info + Batches + Students + Faculty
    // ----------------------------------------------------------
    else {
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
            SELECT b.id AS batch_id, b.name AS batch_name, b.batch_time_slot,
                   b.start_date, b.end_date, b.admin_action,
                   COUNT(sb.student_id) AS enrolled_students
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
            $progress = 0;

            if (strtolower($batch['admin_action']) === 'approved') {
                $active_batches++;
            }

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

            // --------------------------------------------------
            // FIXED STUDENT QUERY (NO DUPLICATES)
            // --------------------------------------------------
            $studentQuery = "
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
                GROUP BY sp.id, u.user_name, u.email, u.phone_number
                ORDER BY u.user_name ASC
            ";

            $sstmt = $conn->prepare($studentQuery);
            $sstmt->bind_param("ii", $course_id, $batch_id);
            $sstmt->execute();
            $studentResult = $sstmt->get_result();

            $students = [];
            while ($student = $studentResult->fetch_assoc()) {
                $students[] = [
                    "student_id" => intval($student['student_id']),
                    "name"       => $student['name'],
                    "email"      => $student['email'],
                    "join_date"  => $student['enrollment_date'],
                    "status"     => ucfirst($student['enrollment_status'] ?? 'Active')
                ];
            }

            $batchData[] = [
                "batch_id"          => $batch['batch_id'],
                "batch_name"        => $batch['batch_name'],
                "batch_time_slot"   => $batch['batch_time_slot'],
                "start_date"        => $batch['start_date'],
                "end_date"          => $batch['end_date'],
                "status"            => ucfirst($batch['admin_action']),
                "completion_percent"=> $progress,
                "enrolled_students" => intval($batch['enrolled_students']),
                "students"          => $students
            ];
        }

        // ---------------------------------------------------
        // FACULTY LIST
        // ---------------------------------------------------
        $faculty = [];
        if ($institute_id > 0) {
            $facultyQuery = "
                SELECT id AS faculty_id, name, email, phone, role, admin_action
                FROM faculty_users
                WHERE institute_id = ? AND admin_action = 'approved'
                ORDER BY name ASC
            ";
            $facultyStmt = $conn->prepare($facultyQuery);
            $facultyStmt->bind_param("i", $institute_id);
            $facultyStmt->execute();
            $facultyResult = $facultyStmt->get_result();

            while ($f = $facultyResult->fetch_assoc()) {
                $faculty[] = [
                    "faculty_id" => intval($f['faculty_id']),
                    "name"       => $f['name'],
                    "email"      => $f['email'],
                    "phone"      => $f['phone'],
                    "role"       => ucfirst($f['role']),
                    "status"     => ucfirst($f['admin_action'])
                ];
            }
        }

        echo json_encode([
            "status"  => true,
            "message" => "Course details with batches, enrolled students, and faculty fetched successfully.",
            "role"    => $role,

            "course"  => $course,
            "batches" => $batchData,
            "active_batches" => $active_batches,
            "faculty" => $faculty
        ], JSON_PRETTY_PRINT);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
