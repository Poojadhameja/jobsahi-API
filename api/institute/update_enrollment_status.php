<?php
require_once '../cors.php';
$decoded = authenticateJWT(['admin', 'institute']);


$input = json_decode(file_get_contents("php://input"), true);
$student_id = intval($input['student_id'] ?? 0);
$status = strtolower(trim($input['status'] ?? ''));

if (!$student_id || !$status) {
    echo json_encode(["status" => false, "message" => "Missing student_id or status"]);
    exit;
}

try {
    $stmt = $conn->prepare("
        UPDATE student_course_enrollments 
        SET status = ?,
        WHERE student_id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("si", $status, $student_id);
    $stmt->execute();

    echo json_encode([
        "status" => true,
        "message" => "Enrollment status updated successfully",
        "affected" => $stmt->affected_rows
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>
