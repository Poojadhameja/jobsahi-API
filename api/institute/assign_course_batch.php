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
    // 🎯 FIXED: FETCH REAL institute_id FROM institute_profiles
    // (NO LOGIC CHANGED)
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

    if (!is_array($student_id)) $student_id = [$student_id];

    if (empty($student_id) || !$course_id || !$batch_id) {
        echo json_encode(["status" => false, "message" => "Missing parameters"]);
        exit;
    }

    // ----------------------------------------------------
    // 🟦 NEW STRICT FILTER:
    // ✔ Institute can only assign THEIR COURSE
    // ✔ Institute can only assign THEIR BATCH
    // (NO LOGIC CHANGED — ONLY FILTER ADDED)
    // ----------------------------------------------------
    if ($role === "institute") {

        // Check course
        $chkCourse = $conn->prepare("
            SELECT id FROM courses 
            WHERE id = ? AND institute_id = ? 
            LIMIT 1
        ");
        $chkCourse->bind_param("ii", $course_id, $institute_id);
        $chkCourse->execute();
        if ($chkCourse->get_result()->num_rows == 0) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized: This course does not belong to your institute"
            ]);
            exit;
        }

        // Check batch → batches.course_id MUST belong to same institute
        $chkBatch = $conn->prepare("
            SELECT b.id
            FROM batches b
            JOIN courses c ON c.id = b.course_id
            WHERE b.id = ? AND c.institute_id = ?
            LIMIT 1
        ");
        $chkBatch->bind_param("ii", $batch_id, $institute_id);
        $chkBatch->execute();
        if ($chkBatch->get_result()->num_rows == 0) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized: This batch does not belong to your institute"
            ]);
            exit;
        }
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

        $check->bind_param("ii", $student_profile_id, $batch_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();

        if ($exists['total'] > 0) {
            $alreadyAssigned[] = $sid;
            continue;
        }

        $stmt->bind_param("ii", $student_profile_id, $course_id);
        $stmt->execute();

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
