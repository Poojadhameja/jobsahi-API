<?php
require_once '../cors.php';
$decoded = authenticateJWT(['admin', 'institute']);
$institute_id = intval($decoded['institute_id'] ?? 0);

try {
    // ✅ Simplified Query
    $sql = "
        SELECT 
            sp.id AS student_id,
            sp.user_id,
            u.user_name AS student_name,
            u.email AS email,
            u.phone_number AS phone,
            sp.trade,
            sp.education,
            e.status AS enrollment_status,
            e.enrollment_date,
            c.id AS course_id,
            c.title AS course_title,
            c.duration AS course_duration,
            sb.batch_id,
            b.name AS batch_name,
            b.start_date,
            b.end_date
        FROM student_profiles sp
        INNER JOIN users u 
            ON sp.user_id = u.id
        INNER JOIN student_course_enrollments e 
            ON sp.id = e.student_id 
            AND e.admin_action = 'approved' 
            AND e.status IN ('enrolled', 'completed')
        INNER JOIN courses c 
            ON e.course_id = c.id 
            AND c.admin_action = 'approved'
            AND c.institute_id = ?
        LEFT JOIN student_batches sb
            ON sp.id = sb.student_id
        LEFT JOIN batches b 
            ON sb.batch_id = b.id 
            AND b.admin_action = 'approved'
        WHERE sp.deleted_at IS NULL
          AND u.status = 'active'
        ORDER BY u.user_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    $current_date = new DateTime();

    while ($row = $result->fetch_assoc()) {

        $progress_percent = 0;
        $status_text = "Not Started";

        // ✅ Calculate progress based on batch dates
        if (!empty($row['start_date']) && !empty($row['end_date'])) {
            try {
                $start_date = new DateTime($row['start_date']);
                $end_date = new DateTime($row['end_date']);

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
                    }
                    $status_text = "Ongoing";
                }
            } catch (Exception $e) {
                // Date parsing failed, skip to fallback
            }
        } 
        // ✅ Fallback to enrollment date (FIXED)
        elseif (!empty($row['enrollment_date'])) {
            try {
                $enrollment_date = new DateTime($row['enrollment_date']);
                $days_since_enrollment = $enrollment_date->diff($current_date)->days;
                
                // ✅ Extract numeric value from course_duration (handles "6 months", "12", etc.)
                $duration_value = 6; // default
                if (!empty($row['course_duration'])) {
                    // Extract first number from string
                    preg_match('/\d+/', $row['course_duration'], $matches);
                    if (!empty($matches[0])) {
                        $duration_value = intval($matches[0]);
                    }
                }
                
                $course_duration_days = $duration_value * 30;
                
                if ($course_duration_days > 0) {
                    if ($days_since_enrollment >= $course_duration_days) {
                        $progress_percent = 100;
                        $status_text = "Completed";
                    } else {
                        $progress_percent = round(($days_since_enrollment / $course_duration_days) * 100, 2);
                        $status_text = "Ongoing";
                    }
                }
            } catch (Exception $e) {
                // Keep default values
            }
        }

        $students[] = [
            "student_id" => $row['student_id'],
            "name" => $row['student_name'],
            "email" => $row['email'],
            "phone" => $row['phone'],
            "trade" => $row['trade'],
            "course" => $row['course_title'] ?? "Not Assigned",
            "batch" => $row['batch_name'] ?? "Not Assigned",
            "progress" => $progress_percent,
            "status" => $status_text
        ];
    }

    echo json_encode([
        "status" => true,
        "count" => count($students),
        "data" => $students
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>