<?php
require_once '../cors.php';
$decoded = authenticateJWT(['admin', 'institute']);
$institute_id = intval($decoded['institute_id'] ?? 0);

$input = json_decode(file_get_contents("php://input"), true);

$student_id = $input['student_id'] ?? [];
$course_id  = intval($input['course_id'] ?? 0);
$batch_id   = intval($input['batch_id'] ?? 0);
$assignment_reason     = trim($input['assignment_reason'] ?? '');

// ✅ Normalize student_id
if (!is_array($student_id)) {
    $student_id = [$student_id];
}

if (empty($student_id) || !$course_id || !$batch_id) {
    echo json_encode(["status" => false, "message" => "Missing parameters"]);
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO student_course_enrollments 
        (student_id, course_id, enrollment_date, status, admin_action)
        VALUES (?, ?, NOW(), 'enrolled', 'approved')
        ON DUPLICATE KEY UPDATE 
        course_id = VALUES(course_id), status = 'enrolled'
    ");

    foreach ($student_id as $sid) {
        $stmt->bind_param("ii", $sid, $course_id);
        $stmt->execute();
    }

    $stmt2 = $conn->prepare("
        INSERT INTO student_batches (student_id, batch_id,assignment_reason, admin_action)
        VALUES (?, ?, ?, 'approved')
        ON DUPLICATE KEY UPDATE batch_id = VALUES(batch_id)
    ");
    foreach ($student_id as $sid) {
        $stmt2->bind_param("iis", $sid, $batch_id, $assignment_reason);
        $stmt2->execute();
    }

    echo json_encode([
        "status" => true,
        "message" => "Students assigned successfully",
        "assigned_count" => count($student_id)
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>
