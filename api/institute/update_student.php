<?php
// update_student.php – Update student batch and course status
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate only Institute or Admin
$decoded = authenticateJWT(['institute', 'admin']);
$role = strtolower($decoded['role']);
$user_id = intval($decoded['user_id']);

// ✅ Allow only PUT method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(["status" => false, "message" => "Only PUT method allowed"]);
    exit;
}

// ✅ Read JSON body
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(["status" => false, "message" => "Invalid JSON input"]);
    exit;
}

$student_id = intval($input['student_id'] ?? 0);
$new_batch_id = intval($input['batch_id'] ?? 0);
$new_status = trim($input['status'] ?? ''); // enrolled / completed / dropped

if ($student_id <= 0) {
    echo json_encode(["status" => false, "message" => "Student ID is required"]);
    exit;
}

try {
    $conn->begin_transaction();

    // ✅ 1. Update course status in student_course_enrollments
    if ($new_status !== '') {
        $stmt = $conn->prepare("
            UPDATE student_course_enrollments 
            SET status = ?, modified_at = NOW() 
            WHERE student_id = ?
        ");
        $stmt->bind_param("si", $new_status, $student_id);
        $stmt->execute();
        $stmt->close();
    }

    // ✅ 2. Update student's batch
    if ($new_batch_id > 0) {

        // 🔍 FIXED: Get only the latest batch row for this student
        $check = $conn->prepare("
            SELECT id 
            FROM student_batches 
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
            // 🔥 UPDATE ONLY THIS ONE ROW (NOT ALL)
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
            // Insert new batch entry
            $stmt = $conn->prepare("
                INSERT INTO student_batches (student_id, batch_id, admin_action) 
                VALUES (?, ?, 'approved')
            ");
            $stmt->bind_param("ii", $student_id, $new_batch_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Student details updated successfully",
        "data" => [
            "student_id" => $student_id,
            "batch_id" => $new_batch_id,
            "status" => $new_status
        ]
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
