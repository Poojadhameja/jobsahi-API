<?php
// update_student.php – Auto-detect course_id from batch, update only selected course
require_once '../cors.php';
require_once '../db.php';

// Authenticate Admin or Institute
$decoded = authenticateJWT(['institute', 'admin']);
$role    = strtolower($decoded['role']);
$user_id = intval($decoded['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["status" => false, "message" => "Only PUT method allowed"]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["status" => false, "message" => "Invalid JSON input"]);
    exit;
}

$student_id   = intval($input['student_id'] ?? 0);
$new_batch_id = intval($input['batch_id'] ?? 0);
$new_status   = trim($input['status'] ?? '');

if ($student_id <= 0) {
    echo json_encode(["status" => false, "message" => "student_id is required"]);
    exit;
}

try {

    $conn->begin_transaction();

    /* =============================================================
       1️⃣ AUTO-DETECT course_id FROM batch_id  (Always reliable)
       ============================================================= */

    $course_id = null;

    if ($new_batch_id > 0) {
        $stmt = $conn->prepare("SELECT course_id FROM batches WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $new_batch_id);
        $stmt->execute();
        $stmt->bind_result($course_id);
        $stmt->fetch();
        $stmt->close();
    }

    if (!$course_id) {
        throw new Exception("Batch is not linked to any course, cannot detect course_id");
    }

    /* =============================================================
       2️⃣ UPDATE ONLY THIS COURSE STATUS
       ============================================================= */
    if ($new_status !== '') {

        $stmt = $conn->prepare("
            UPDATE student_course_enrollments
            SET status = ?, modified_at = NOW()
            WHERE student_id = ? AND course_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("sii", $new_status, $student_id, $course_id);
        $stmt->execute();
        $stmt->close();
    }

    /* =============================================================
       3️⃣ UPDATE ONLY THE LATEST BATCH ROW FOR THE STUDENT
       ============================================================= */
    if ($new_batch_id > 0) {

        $check = $conn->prepare("
            SELECT id FROM student_batches
            WHERE student_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $check->bind_param("i", $student_id);
        $check->execute();
        $check->bind_result($batch_row_id);
        $exists = $check->fetch();
        $check->close();

        if ($exists && $batch_row_id > 0) {

            $stmt = $conn->prepare("
                UPDATE student_batches
                SET batch_id = ?, admin_action = 'approved'
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $new_batch_id, $batch_row_id);
            $stmt->execute();
            $stmt->close();

        } else {

            $stmt = $conn->prepare("
                INSERT INTO student_batches (student_id, batch_id, admin_action)
                VALUES (?, ?, 'approved')
            ");
            $stmt->bind_param("ii", $student_id, $new_batch_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    /* =============================================================
       4️⃣ FETCH UPDATED BATCH NAME FOR UI
       ============================================================= */
    $batch_name = null;
    if ($new_batch_id > 0) {

        $stmt = $conn->prepare("SELECT name FROM batches WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $new_batch_id);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $batch_name = $row['name'];
        }
        $stmt->close();
    }

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Student details updated successfully",
        "data" => [
            "student_id" => $student_id,
            "course_id"  => $course_id,    // Auto-detected
            "batch_id"   => $new_batch_id,
            "batch_name" => $batch_name,
            "status"     => $new_status
        ]
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
