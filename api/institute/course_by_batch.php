<?php
// course_by_batch.php — Fetch all courses with batch stats and real-time progress calculation
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // -------------------------------
    // ✅ Determine institute_id automatically from token
    // -------------------------------
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));
    $institute_id = 0;

    if ($role === 'institute') {
        // Fetch institute_id using user_id
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();
    } elseif ($role === 'admin') {
        // Admin can view all courses
        $institute_id = 0;
    }

    // ❌ Stop if institute_id is missing
    if ($role === 'institute' && $institute_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Institute ID missing or invalid in token"
        ]);
        exit;
    }

    // -------------------------------
    // ✅ Fetch approved courses for institute
    // -------------------------------
    if ($role === 'institute') {
        $courseSql = "
            SELECT 
                id AS course_id,
                title AS course_title,
                instructor_name,
                duration
            FROM courses
            WHERE institute_id = ? 
              AND admin_action = 'approved'
            ORDER BY created_at DESC
        ";
        $courseStmt = $conn->prepare($courseSql);
        $courseStmt->bind_param("i", $institute_id);
    } else {
        $courseSql = "
            SELECT 
                id AS course_id,
                title AS course_title,
                instructor_name,
                duration
            FROM courses
            WHERE admin_action = 'approved'
            ORDER BY created_at DESC
        ";
        $courseStmt = $conn->prepare($courseSql);
    }

    $courseStmt->execute();
    $courseResult = $courseStmt->get_result();

    $courses = [];
    $current_date = new DateTime();

    // -------------------------------
    // ✅ Loop through each course and calculate stats
    // -------------------------------
    while ($course = $courseResult->fetch_assoc()) {
        $course_id = intval($course['course_id']);

        // Fetch all batches for this course
        $batchSql = "
            SELECT id, start_date, end_date, admin_action
            FROM batches
            WHERE course_id = ?
        ";
        $batchStmt = $conn->prepare($batchSql);
        $batchStmt->bind_param("i", $course_id);
        $batchStmt->execute();
        $batches = $batchStmt->get_result();

        $total_batches = 0;
        $active_batches = 0;
        $progress_sum = 0;
        $progress_count = 0;
        $status_list = [];

        // -------------------------------
        // ✅ Calculate progress for each batch
        // -------------------------------
        while ($batch = $batches->fetch_assoc()) {
            $total_batches++;
            if (strtolower($batch['admin_action']) === 'approved') {
                $active_batches++;
            }

            $progress_percent = 0;
            $status_text = "Not Started";

            if (!empty($batch['start_date']) && !empty($batch['end_date'])) {
                try {
                    $start_date = new DateTime($batch['start_date']);
                    $end_date = new DateTime($batch['end_date']);

                    if ($current_date < $start_date) {
                        $progress_percent = 0;
                        $status_text = "Not Started";
                    } elseif ($current_date >= $end_date) {
                        $progress_percent = 100;
                        $status_text = "Completed";
                    } else {
                        $total_days = $start_date->diff($end_date)->days;
                        $elapsed_days = $start_date->diff($current_date)->days;

                        if ($total_days > 0) {
                            $progress_percent = round(($elapsed_days / $total_days) * 100, 2);
                            $status_text = "Ongoing";
                        }
                    }
                } catch (Exception $e) {
                    $progress_percent = 0;
                    $status_text = "Not Started";
                }
            } 
            // ✅ Fallback if no batch dates
            elseif (!empty($course['duration'])) {
                preg_match('/\d+/', $course['duration'], $matches);
                $duration_value = !empty($matches[0]) ? intval($matches[0]) : 6;
                $progress_percent = round(($duration_value / 12) * 100, 2);
                $status_text = "Ongoing";
            }

            $progress_sum += $progress_percent;
            $progress_count++;
            $status_list[] = $status_text;
        }

        // ✅ Calculate average progress
        $overall_progress = ($progress_count > 0)
            ? round($progress_sum / $progress_count, 2)
            : 0;

        // ✅ Determine course status
        if ($overall_progress == 0) {
            $course_status = "Not Started";
        } elseif ($overall_progress == 100) {
            $course_status = "Completed";
        } else {
            $course_status = "Ongoing";
        }

        // Add to output list
        $courses[] = [
            "course_id"        => $course['course_id'],
            "course_title"     => $course['course_title'],
            "instructor_name"  => $course['instructor_name'],
            "total_batches"    => $total_batches,
            "active_batches"   => $active_batches,
            "overall_progress" => $overall_progress
        ];

        $batchStmt->close();
    }

    // -------------------------------
    // ✅ Final Response
    // -------------------------------
    echo json_encode([
        "status"  => true,
        "message" => "Courses with batch progress fetched successfully.",
        "role"    => $role,
        "count"   => count($courses),
        "courses" => $courses
    ], JSON_PRETTY_PRINT);

    $courseStmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
