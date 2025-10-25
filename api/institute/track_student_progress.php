<?php
require_once '../cors.php';
$decoded = authenticateJWT(['admin', 'institute']);
$institute_id = intval($decoded['institute_id'] ?? 0);

try {
    $sql = "
        SELECT 
            sp.id AS student_id,
            u.user_name,
            c.title AS course_title,
            b.name AS batch_name,
            b.start_date,
            b.end_date,
            e.status AS enrollment_status,
            e.enrollment_date
        FROM student_profiles sp
        INNER JOIN users u ON sp.user_id = u.id
        LEFT JOIN student_course_enrollments e ON sp.id = e.student_id
        LEFT JOIN courses c ON e.course_id = c.id
        LEFT JOIN batches b ON c.id = b.course_id
        WHERE c.institute_id = ?
          AND e.admin_action = 'approved'
        ORDER BY u.user_name
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    $total_progress = 0;
    $student_count = 0;

    while ($row = $result->fetch_assoc()) {

        // ✅ Calculate progress_percent based on start_date and end_date
        $progress_percent = 0;
        $status_text = "Not Started";

        if (!empty($row['start_date']) && !empty($row['end_date'])) {
            $start = strtotime($row['start_date']);
            $end   = strtotime($row['end_date']);
            $today = strtotime(date('Y-m-d'));

            if ($end > $start) {
                if ($today <= $start) {
                    $progress_percent = 0;
                    $status_text = "Not Started";
                } elseif ($today >= $end) {
                    $progress_percent = 100;
                    $status_text = "Completed";
                } else {
                    $total_days = ($end - $start) / (60 * 60 * 24);
                    $elapsed_days = ($today - $start) / (60 * 60 * 24);
                    $progress_percent = round(($elapsed_days / $total_days) * 100, 2);
                    $status_text = "Ongoing";
                }
            }
        }

        // ✅ Accumulate progress for average
        $total_progress += $progress_percent;
        $student_count++;

        // ✅ Append data
        $records[] = [
            "student_id" => $row['student_id'],
            "user_name" => $row['user_name'],
            "course" => $row['course_title'],
            "batch" => $row['batch_name'],
            "progress" => $progress_percent,
            "status" => $status_text
        ];
    }

    // ✅ Calculate Average Progress
    $average_progress = $student_count > 0 ? round($total_progress / $student_count, 2) : 0;

    echo json_encode([
        "status" => true,
        "count" => count($records),
        "average_progress" => $average_progress,
        "data" => $records
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>
