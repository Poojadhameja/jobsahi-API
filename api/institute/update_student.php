<?php
// update_student.php – Update student batch and status
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
$new_status = trim($input['status'] ?? '');

if ($student_id <= 0) {
    echo json_encode(["status" => false, "message" => "Student ID is required"]);
    exit;
}

try {
    $conn->begin_transaction();

    // ✅ 1. Update user status (active/inactive)
    if ($new_status !== '') {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = (
            SELECT user_id FROM student_profiles WHERE id = ?
        )");
        $stmt->bind_param("si", $new_status, $student_id);
        $stmt->execute();
        $stmt->close();
    }

    // ✅ 2. Update student's batch
    if ($new_batch_id > 0) {
        // Check if entry exists
        $check = $conn->prepare("SELECT id FROM student_batches WHERE student_id = ?");
        $check->bind_param("i", $student_id);
        $check->execute();
        $check->store_result();
        $exists = ($check->num_rows > 0);
        $check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE student_batches SET batch_id = ?, admin_action = 'approved' WHERE student_id = ?");
            $stmt->bind_param("ii", $new_batch_id, $student_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO student_batches (student_id, batch_id, admin_action) VALUES (?, ?, 'approved')");
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
