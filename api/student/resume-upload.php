<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT for student role
$studentData = authenticateJWT('student'); // decoded JWT payload

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

// ✅ Directory Setup
$upload_dir = realpath(__DIR__ . '/../uploads/resume/') . DIRECTORY_SEPARATOR;
$relative_path = '/api/uploads/resume/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ✅ Allowed file extensions
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

// ✅ File Upload Helper
function uploadResume($key, $upload_dir, $relative_path, $allowed_extensions)
{
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $_FILES[$key]['tmp_name'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions)) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX"
        ]);
        exit;
    }

    $filename = 'resume_' . time() . '.' . $ext;
    $destination = $upload_dir . $filename;

    if (move_uploaded_file($tmp, $destination)) {
        return $relative_path . $filename;
    } else {
        echo json_encode(["status" => false, "message" => "File upload failed"]);
        exit;
    }
}

// ✅ Check for file upload or JSON input
$resumePath = null;

if (isset($_FILES['resume'])) {
    $resumePath = uploadResume('resume', $upload_dir, $relative_path, $allowed_extensions);
} else {
    $input = json_decode(file_get_contents("php://input"), true);
    $resumePath = $input['resume'] ?? null;
}

if (!$resumePath) {
    echo json_encode(["message" => "Resume path or file is required", "status" => false]);
    exit;
}

// ✅ Extract Student ID from Token
$userId = $studentData['id'] ?? ($studentData['user_id'] ?? null);

if ($userId === null) {
    echo json_encode(["message" => "Unauthorized: User ID missing in token", "status" => false]);
    exit;
}

// ✅ Find Student Profile ID from user_id
$getStudent = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL");
$getStudent->bind_param("i", $userId);
$getStudent->execute();
$res = $getStudent->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["message" => "No student profile found for this user", "status" => false]);
    exit;
}

$studentProfile = $res->fetch_assoc();
$studentId = intval($studentProfile['id']); // real student_profiles.id

// ✅ Check if same resume already exists
$check_sql = "SELECT resume FROM student_profiles WHERE id = ? AND deleted_at IS NULL";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $studentId);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_bind_result($check_stmt, $existingResume);
mysqli_stmt_fetch($check_stmt);
mysqli_stmt_close($check_stmt);

if ($existingResume === $resumePath) {
    echo json_encode([
        "message" => "Resume already up to date",
        "status" => true,
        "resume_path" => $resumePath
    ]);
    exit;
}

// ✅ Update Resume in Database
$sql = "UPDATE student_profiles 
        SET resume = ?, updated_at = NOW() 
        WHERE id = ? AND deleted_at IS NULL";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $resumePath, $studentId);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        "message" => "Resume updated successfully",
        "status" => true,
        "resume_path" => $resumePath
    ]);
} else {
    echo json_encode([
        "message" => "Query failed: " . mysqli_error($conn),
        "status" => false
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
