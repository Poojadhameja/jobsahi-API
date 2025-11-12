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
if (!is_array($student_id)) $student_id = [$student_id];

if (empty($student_id) || !$course_id || !$batch_id) {
    echo json_encode(["status" => false, "message" => "Missing parameters"]);
    exit;
}

try {
    // 🔹 Map user_id → student_profile_id
    $profileMap = [];
    $userIds = implode(',', array_map('intval', $student_id));
    $res = $conn->query("SELECT id, user_id FROM student_profiles WHERE user_id IN ($userIds)");
    while ($row = $res->fetch_assoc()) {
        $profileMap[$row['user_id']] = $row['id'];
    }

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
        $student_profile_id = $profileMap[$sid] ?? 0;
        if ($student_profile_id <= 0) continue;

        // ✅ Check if student already assigned
        $check->bind_param("ii", $student_profile_id, $batch_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

        if ($exists['total'] > 0) {
            $alreadyAssigned[] = $sid;
            continue;
        }

        // ✅ Proceed with assignment
        $stmt->bind_param("ii", $student_profile_id, $course_id);
        $stmt->execute();

        $stmt2->bind_param("iis", $student_profile_id, $batch_id, $assignment_reason);
        $stmt2->execute();

        $assignedCount++;
    }

    // ✅ Prepare final message
    if (!empty($alreadyAssigned)) {
        echo json_encode([
            "status" => true,
            "message" => "Some students were already assigned to this batch.",
            "assigned_count" => $assignedCount,
            "already_assigned" => $alreadyAssigned
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
