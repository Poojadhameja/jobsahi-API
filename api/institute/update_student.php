<?php
// update_student.php – Fixed version (Preserves batch history)
require_once '../cors.php';
require_once '../db.php';

// Authenticate Admin or Institute
$decoded = authenticateJWT(['institute', 'admin']);
$role = strtolower($decoded['role']);
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

$student_id = intval($input['student_id'] ?? 0);
$new_batch_id = intval($input['batch_id'] ?? 0);
$new_status = trim($input['status'] ?? '');

if ($student_id <= 0) {
    echo json_encode(["status" => false, "message" => "student_id is required"]);
    exit;
}

try {
    $conn->begin_transaction();

    /* =============================================================
       1️⃣ AUTO-DETECT course_id FROM batch_id
       ============================================================= */
    $course_id = null;

    if ($new_batch_id > 0) {
        $stmt = $conn->prepare("SELECT course_id FROM batches WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $new_batch_id);
        $stmt->execute();
        $stmt->bind_result($course_id);
        $stmt->fetch();
        $stmt->close();

        if (!$course_id) {
            throw new Exception("Batch is not linked to any course, cannot detect course_id");
        }
    }

    /* =============================================================
       2️⃣ UPDATE STATUS IN student_course_enrollments
       ============================================================= */
    if ($new_status !== '' && $course_id) {
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
       3️⃣ UPDATE OR INSERT BATCH ASSIGNMENT (PRESERVE HISTORY)
       ============================================================= */
    if ($new_batch_id > 0 && $course_id) {

        // Check if student already has this exact batch assignment
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM student_batches 
            WHERE student_id = ? AND batch_id = ?
        ");
        $check_stmt->bind_param("ii", $student_id, $new_batch_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $row = $check_result->fetch_assoc();
        $exists = $row['count'] > 0;
        $check_stmt->close();

        if ($exists) {
            // Update existing batch assignment
            $stmt = $conn->prepare("
                UPDATE student_batches 
                SET assignment_reason = 'Batch reassignment', 
                    admin_action = 'approved'
                WHERE student_id = ? AND batch_id = ?
            ");
            $stmt->bind_param("ii", $student_id, $new_batch_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Check if there's a different batch for the same course
            $old_batch_stmt = $conn->prepare("
                SELECT sb.batch_id 
                FROM student_batches sb
                INNER JOIN batches b ON sb.batch_id = b.id
                WHERE sb.student_id = ? AND b.course_id = ?
                LIMIT 1
            ");
            $old_batch_stmt->bind_param("ii", $student_id, $course_id);
            $old_batch_stmt->execute();
            $old_batch_result = $old_batch_stmt->get_result();
            $has_old_batch = $old_batch_result->num_rows > 0;
            $old_batch_stmt->close();

            if ($has_old_batch) {
                // Mark old batch assignments as inactive (if you have such a column)
                // OR delete them if you don't want history
                // For now, we'll delete old batch for same course to avoid duplicates
                $delete_stmt = $conn->prepare("
                    DELETE sb
                    FROM student_batches sb
                    INNER JOIN batches b ON sb.batch_id = b.id
                    WHERE sb.student_id = ? AND b.course_id = ?
                ");
                $delete_stmt->bind_param("ii", $student_id, $course_id);
                $delete_stmt->execute();
                $delete_stmt->close();
            }

            // Insert new batch assignment
            $stmt = $conn->prepare("
                INSERT INTO student_batches (student_id, batch_id, assignment_reason, admin_action)
                VALUES (?, ?, 'Batch reassignment', 'approved')
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
            "course_id" => $course_id,
            "batch_id" => $new_batch_id,
            "batch_name" => $batch_name,
            "status" => $new_status
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