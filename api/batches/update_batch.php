<?php
// update_batch.php - Update batch details (Auto-approve mode for Admin/Institute)
require_once '../cors.php';

// ✅ Authenticate JWT (allow only admin and institute)
$decoded = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role'] ?? '');

// ✅ Validate batch_id
if (!isset($_GET['batch_id']) || !is_numeric($_GET['batch_id'])) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid or missing batch_id"
    ]);
    exit();
}

$batch_id = intval($_GET['batch_id']);

// ✅ Parse incoming JSON body
$data = json_decode(file_get_contents("php://input"), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON input"
    ]);
    exit();
}

// ✅ Extract fields safely
$course_id       = isset($data['course_id']) ? intval($data['course_id']) : 0;
$name            = isset($data['name']) ? trim($data['name']) : '';
$batch_time_slot = isset($data['batch_time_slot']) ? trim($data['batch_time_slot']) : '';
$start_date      = isset($data['start_date']) ? $data['start_date'] : null;
$end_date        = isset($data['end_date']) ? $data['end_date'] : null;
$media           = isset($data['media']) ? $data['media'] : null;
$instructor_id   = isset($data['instructor_id']) ? intval($data['instructor_id']) : 0;

// ✅ Force approved mode
$admin_action = 'approved';

// ✅ Validate mandatory fields
if ($course_id <= 0 || empty($name) || empty($batch_time_slot) || empty($start_date) || empty($end_date) || $instructor_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "All fields including valid instructor_id are required."
    ]);
    exit();
}

// ✅ Check batch existence
$check_stmt = $conn->prepare("SELECT id FROM batches WHERE id = ?");
$check_stmt->bind_param("i", $batch_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Batch not found."
    ]);
    exit();
}
$check_stmt->close();

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

// ✅ Convert media to JSON if array
if (is_array($media)) {
    $media = json_encode($media, JSON_UNESCAPED_UNICODE);
}

// ✅ Prepare update query
$sql = "
    UPDATE batches SET 
        course_id = ?, 
        name = ?, 
        batch_time_slot = ?, 
        start_date = ?, 
        end_date = ?, 
        media = ?, 
        instructor_id = ?, 
        admin_action = ?
    WHERE id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "issssssis",
    $course_id,
    $name,
    $batch_time_slot,
    $start_date,
    $end_date,
    $media,
    $instructor_id,
    $admin_action,
    $batch_id
);

// ✅ Execute update
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => true,
            "message" => "Batch updated and approved successfully.",
            "batch_id" => $batch_id
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "No changes detected or invalid data provided."
        ]);
    }
} else {
    echo json_encode([
        "status" => false,
        "message" => "Failed to update batch.",
        "error" => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
