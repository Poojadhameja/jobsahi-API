<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';  // Include your JWT helper
require_once '../auth/auth_middleware.php';  // Include your middleware

// Authenticate and allow 'student' role
authenticateJWT('student');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["message" => "Only PUT requests allowed", "status" => false]);
    exit;
}

include "../db.php";

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['id'])) {
    echo json_encode(["message" => "Invalid input, student id required", "status" => false]);
    exit;
}

$id = intval($input['id']);
$skills = $input['skills'] ?? "";
$education = $input['education'] ?? "";
$resume = $input['resume'] ?? "";
$portfolio_link = $input['portfolio_link'] ?? "";
$linkedin_url = $input['linkedin_url'] ?? "";
$dob = $input['dob'] ?? "";
$gender = $input['gender'] ?? "";
$job_type = $input['job_type'] ?? "";
$trade = $input['trade'] ?? "";
$location = $input['location'] ?? "";

$sql = "UPDATE student_profiles SET 
            skills = ?, 
            education = ?, 
            resume = ?, 
            portfolio_link = ?, 
            linkedin_url = ?, 
            dob = ?, 
            gender = ?, 
            job_type = ?, 
            trade = ?, 
            location = ?, 
            modified_at = NOW()
        WHERE id = ? AND deleted_at IS NULL";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    "ssssssssssi",
    $skills,
    $education,
    $resume,
    $portfolio_link,
    $linkedin_url,
    $dob,
    $gender,
    $job_type,
    $trade,
    $location,
    $id
);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            "message" => "Student profile updated successfully",
            "status" => true
        ]);
    } else {
        echo json_encode([
            "message" => "No record updated. Check ID or deleted_at",
            "status" => false
        ]);
    }
} else {
    echo json_encode([
        "message" => "Update failed: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
