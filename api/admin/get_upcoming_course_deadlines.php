<?php
require_once '../cors.php';
require_once '../db.php';

// ADMIN ONLY
$decoded = authenticateJWT(['admin']);
if (!$decoded) {
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

// Get days parameter (default: 30 days)
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

try {
    // Calculate date range
    $today = date('Y-m-d');
    $futureDate = date('Y-m-d', strtotime("+{$days} days"));

    // Query to get courses with upcoming batch deadlines
    $query = "
        SELECT 
            c.id AS course_id,
            c.title AS course_name,
            b.id AS batch_id,
            b.name AS batch_name,
            b.end_date,
            COUNT(DISTINCT sce.student_id) AS student_count,
            DATEDIFF(b.end_date, CURDATE()) AS days_remaining,
            CASE 
                WHEN b.end_date < CURDATE() THEN 'expired'
                WHEN DATEDIFF(b.end_date, CURDATE()) <= 7 THEN 'alert_sent'
                ELSE 'scheduled'
            END AS status
        FROM courses c
        INNER JOIN batches b ON c.id = b.course_id
        LEFT JOIN student_course_enrollments sce ON c.id = sce.course_id AND b.id = sce.batch_id
        WHERE b.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND c.status = 'active'
        GROUP BY c.id, c.title, b.id, b.name, b.end_date
        ORDER BY b.end_date ASC
        LIMIT 50
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $deadlines = [];
    while ($row = $result->fetch_assoc()) {
        // Format deadline text
        $daysRemaining = intval($row['days_remaining']);
        $deadlineText = '';
        if ($daysRemaining < 0) {
            $deadlineText = 'Expired';
        } elseif ($daysRemaining === 0) {
            $deadlineText = 'Today';
        } elseif ($daysRemaining === 1) {
            $deadlineText = '1 day';
        } elseif ($daysRemaining < 7) {
            $deadlineText = "{$daysRemaining} days";
        } elseif ($daysRemaining < 30) {
            $weeks = ceil($daysRemaining / 7);
            $deadlineText = "{$weeks} week" . ($weeks > 1 ? 's' : '');
        } else {
            $months = ceil($daysRemaining / 30);
            $deadlineText = "{$months} month" . ($months > 1 ? 's' : '');
        }

        $deadlines[] = [
            'courseId' => intval($row['course_id']),
            'courseName' => $row['course_name'],
            'batchId' => intval($row['batch_id']),
            'batchName' => $row['batch_name'],
            'deadline' => $deadlineText,
            'deadlineDate' => $row['end_date'],
            'daysRemaining' => $daysRemaining,
            'studentCount' => intval($row['student_count']),
            'status' => $row['status']
        ];
    }

    $stmt->close();

    echo json_encode([
        "status" => true,
        "data" => $deadlines,
        "count" => count($deadlines)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>

