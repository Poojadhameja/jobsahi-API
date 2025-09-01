<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate JWT for student role
$studentData = authenticateJWT('student'); // decoded JWT payload

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$resumePath = $input['resume'] ?? null;

if (!$resumePath) {
    echo json_encode(["message" => "Resume path is required", "status" => false]);
    exit;
}

// ✅ Use student ID directly from JWT (do not rely on frontend input)
$studentId = $studentData['id'] ?? ($studentData['student_id'] ?? ($studentData['user_id'] ?? null));

if ($studentId === null) {
    echo json_encode(["message" => "Unauthorized: Student ID missing in token", "status" => false]);
    exit;
}

$sql = "UPDATE student_profiles 
        SET resume = ?, modified_at = NOW() 
        WHERE id = ? AND deleted_at IS NULL";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $resumePath, $studentId);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            "message" => "Resume updated successfully",
            "status" => true,
            "resume_path" => $resumePath
        ]);
    } else {
        echo json_encode([
            "message" => "No rows updated (check if profile exists or same value submitted)",
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
