<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

include "../config.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['id'])) {
    echo json_encode(["message" => "Student ID is required", "status" => false]);
    exit;
}

$id = intval($input['id']);
$resumePath = $input['resume'] ?? null;

if (!$resumePath) {
    echo json_encode(["message" => "Resume path is required", "status" => false]);
    exit;
}

$sql = "UPDATE student_profiles 
        SET resume = ?, modified_at = NOW() 
        WHERE id = ? AND deleted_at IS NULL";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $resumePath, $id);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            "message" => "Resume updated successfully",
            "status" => true,
            "resume_path" => $resumePath
        ]);
    } else {
        echo json_encode([
            "message" => "No rows updated (check if ID exists and deleted_at is NULL, or same value submitted)",
            "status" => false
        ]);
    }
} else {
    echo json_encode([
        "message" => "Query failed: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
