<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ----------------------------------------------------
    // 🔐 AUTHENTICATE JWT (admin or institute)
    // ----------------------------------------------------
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // Default 0
    $institute_id = 0;

    // ----------------------------------------------------
    // 🎯 FIXED: FETCH institute_id PROPERLY FOR INSTITUTE USER
    // ----------------------------------------------------
    if ($role === 'institute') {
        $stmtInst = $conn->prepare("
            SELECT id 
            FROM institute_profiles 
            WHERE user_id = ? 
            LIMIT 1
        ");
        $stmtInst->bind_param("i", $user_id);
        $stmtInst->execute();
        $resInst = $stmtInst->get_result();

        if ($rowInst = $resInst->fetch_assoc()) {
            $institute_id = intval($rowInst['id']);
        }

        $stmtInst->close();

        // ❌ If switching login and institute_id is missing
        if ($institute_id <= 0) {
            echo json_encode([
                "status" => false,
                "message" => "Invalid institute account. Institute ID not found."
            ]);
            exit;
        }
    }

    // ----------------------------------------------------
    // 🔽 INPUT HANDLING (UNCHANGED)
    // ----------------------------------------------------
    $input = json_decode(file_get_contents("php://input"), true);

    $student_id = $input['student_id'] ?? [];
    $course_id  = intval($input['course_id'] ?? 0);
    $batch_id   = intval($input['batch_id'] ?? 0);
    $assignment_reason = trim($input['assignment_reason'] ?? '');

    // Normalize
    if (!is_array($student_id)) $student_id = [$student_id];

    if (empty($student_id) || !$course_id || !$batch_id) {
        echo json_encode(["status" => false, "message" => "Missing parameters"]);
        exit;
    }

    // ----------------------------------------------------
    // 🔽 MAP student user_id → student_profile_id
    // ----------------------------------------------------
    $profileMap = [];
    $userIds = implode(',', array_map('intval', $student_id));

    $res = $conn->query("
        SELECT id, user_id 
        FROM student_profiles 
        WHERE user_id IN ($userIds)
    ");

    while ($row = $res->fetch_assoc()) {
        $profileMap[$row['user_id']] = $row['id'];
    }

    // ----------------------------------------------------
    // 🔽 PREPARED STATEMENTS (UNCHANGED)
    // ----------------------------------------------------
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

    // ----------------------------------------------------
    // 🔽 ASSIGN LOOP (UNCHANGED)
    // ----------------------------------------------------
    foreach ($student_id as $sid) {
        $student_profile_id = $profileMap[$sid] ?? 0;
        if ($student_profile_id <= 0) continue;

        // Check if already assigned
        $check->bind_param("ii", $student_profile_id, $batch_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

        if ($exists['total'] > 0) {
            $alreadyAssigned[] = $sid;
            continue;
        }

        // Assign course
        $stmt->bind_param("ii", $student_profile_id, $course_id);
        $stmt->execute();

        // Assign batch
        $stmt2->bind_param("iis", $student_profile_id, $batch_id, $assignment_reason);
        $stmt2->execute();

        $assignedCount++;
    }

    // ----------------------------------------------------
    // 🔽 FINAL RESPONSE (UNCHANGED)
    // ----------------------------------------------------
    if (!empty($alreadyAssigned)) {
        echo json_encode([
            "status" => true,
            "message" => "These students were already assigned to this batch.",
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
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
