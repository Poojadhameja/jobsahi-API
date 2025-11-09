<?php
require_once '../cors.php';
require_once '../db.php';

$decoded = authenticateJWT(['admin', 'institute']);
$institute_id = intval($decoded['institute_id'] ?? 0);

$input = json_decode(file_get_contents("php://input"), true);

$student_id = $input['student_id'] ?? [];
$course_id  = intval($input['course_id'] ?? 0);
$batch_id   = intval($input['batch_id'] ?? 0);
$assignment_reason = trim($input['assignment_reason'] ?? '');

// ✅ Normalize student_id
if (!is_array($student_id)) {
    $student_id = [$student_id];
}

if (empty($student_id) || !$course_id || !$batch_id) {
    echo json_encode(["status" => false, "message" => "Missing parameters"]);
    exit;
}

try {
    // ✅ Prepared statements
    $stmt = $conn->prepare("
        INSERT INTO student_course_enrollments 
        (student_id, course_id, enrollment_date, status, admin_action)
        VALUES (?, ?, NOW(), 'enrolled', 'approved')
        ON DUPLICATE KEY UPDATE 
        course_id = VALUES(course_id), status = 'enrolled'
    ");

    $stmt2 = $conn->prepare("
        INSERT INTO student_batches (student_id, batch_id, assignment_reason, admin_action)
        VALUES (?, ?, ?, 'approved')
        ON DUPLICATE KEY UPDATE 
        batch_id = VALUES(batch_id),
        assignment_reason = VALUES(assignment_reason)
    ");

    $check = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM student_batches 
        WHERE student_id = ? AND batch_id = ? AND admin_action = 'approved'
    ");

    $alreadyAssigned = [];
    $assignedCount = 0;

    foreach ($student_id as $sid) {
        // ✅ Check if student already assigned
        $check->bind_param("ii", $sid, $batch_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

        if ($exists['total'] > 0) {
            $alreadyAssigned[] = $sid;
            continue; // Skip insertion
        }

        // ✅ Proceed with assignment if not already assigned
        $stmt->bind_param("ii", $sid, $course_id);
        $stmt->execute();

        $stmt2->bind_param("iis", $sid, $batch_id, $assignment_reason);
        $stmt2->execute();

        $assignedCount++;
    }

    // ✅ Prepare final message
    if (!empty($alreadyAssigned)) {
        echo json_encode([
            "status" => true,
            "message" => "This students were already assigned to this batch.",
            "assigned_count" => $assignedCount,
            "already_assigned" => $alreadyAssigned,
            "note" => "Students already assigned to this batch were skipped."
        ]);
    } else {
        echo json_encode([
            "status" => true,
            "message" => "Students assigned successfully.",
            "assigned_count" => $assignedCount
        ]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>
