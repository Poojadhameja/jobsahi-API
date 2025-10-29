<?php
// create_batch.php - Create new batch (Admin, Institute access only)
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role']);

    // ✅ Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            "status" => false,
            "message" => "Invalid request method. Only POST allowed."
        ]);
        exit();
    }

    // ✅ Read JSON body
    $data = json_decode(file_get_contents("php://input"), true);

    // ✅ Extract and sanitize fields
    $course_id       = isset($data['course_id']) ? intval($data['course_id']) : 0;
    $name            = isset($data['name']) ? trim($data['name']) : '';
    $batch_time_slot = isset($data['batch_time_slot']) ? trim($data['batch_time_slot']) : '';
    $start_date      = isset($data['start_date']) ? $data['start_date'] : null;
    $end_date        = isset($data['end_date']) ? $data['end_date'] : null;
    $instructor_id   = isset($data['instructor_id']) ? intval($data['instructor_id']) : 0;
    $admin_action    = "approved"; // default status

    // ✅ Basic validation for required fields
    if ($course_id <= 0 || empty($name) || empty($batch_time_slot) || empty($start_date) || empty($end_date) || $instructor_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "All fields including valid instructor_id are required."
        ]);
        exit();
    }

    // ✅ Validate course_id exists
    $check_course = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $check_course->bind_param("i", $course_id);
    $check_course->execute();
    if ($check_course->get_result()->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Invalid course_id. Course not found."]);
        exit();
    }
    $check_course->close();

    // ✅ Validate instructor_id exists
    $check_instructor = $conn->prepare("SELECT id FROM faculty_users WHERE id = ?");
    $check_instructor->bind_param("i", $instructor_id);
    $check_instructor->execute();
    if ($check_instructor->get_result()->num_rows === 0) {
        echo json_encode(["status" => false, "message" => "Invalid instructor_id. Faculty not found."]);
        exit();
    }
    $check_instructor->close();

    // ✅ Insert new batch
    // ✅ Insert new batch
$stmt = $conn->prepare("
    INSERT INTO batches 
    (course_id, name, batch_time_slot, start_date, end_date, instructor_id, admin_action)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issssis", // ✅ Corrected count
    $course_id,
    $name,
    $batch_time_slot,
    $start_date,
    $end_date,
    $instructor_id,
    $admin_action
);


    // ✅ Execute insert
    if ($stmt->execute()) {
        echo json_encode([
            "status"   => true,
            "message"  => "Batch created successfully.",
            "batch_id" => $stmt->insert_id
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "status"  => false,
            "message" => "Failed to create batch.",
            "error"   => $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
