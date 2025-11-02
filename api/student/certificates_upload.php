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
$upload_dir = realpath(__DIR__ . '/../uploads/student_certificate/') . DIRECTORY_SEPARATOR;
$relative_path = '/uploads/student_certificate/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ✅ Allowed file types
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

// ✅ File Upload Helper
function uploadCertificate($key, $upload_dir, $relative_path, $allowed_extensions)
{
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $_FILES[$key]['tmp_name'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions)) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid file type. Allowed: PDF, JPG, JPEG, PNG, DOC, DOCX"
        ]);
        exit;
    }

    $filename = 'certificate_' . time() . '.' . $ext;
    $destination = $upload_dir . $filename;

    if (move_uploaded_file($tmp, $destination)) {
        return $relative_path . $filename;
    } else {
        echo json_encode(["status" => false, "message" => "File upload failed"]);
        exit;
    }
}

// ✅ Check for file upload or JSON input
$certificatePath = null;

if (isset($_FILES['certificate'])) {
    // File upload mode
    $certificatePath = uploadCertificate('certificate', $upload_dir, $relative_path, $allowed_extensions);
} else {
    // JSON mode (in case you send a direct URL)
    $input = json_decode(file_get_contents("php://input"), true);
    $certificatePath = $input['certificates'] ?? null;
}

if (!$certificatePath) {
    echo json_encode(["message" => "Certificate file or URL is required", "status" => false]);
    exit;
}

// ✅ Extract Student ID from Token
$userId = $studentData['id'] ?? ($studentData['user_id'] ?? null);

if ($userId === null) {
    echo json_encode(["message" => "Unauthorized: User ID missing in token", "status" => false]);
    exit;
}

// ✅ Get student_profile ID using user_id
$getStudent = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL");
$getStudent->bind_param("i", $userId);
$getStudent->execute();
$res = $getStudent->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["message" => "No student profile found for this user", "status" => false]);
    exit;
}

$studentProfile = $res->fetch_assoc();
$studentId = intval($studentProfile['id']); // Actual student_profiles.id

// ✅ Update student profile with certificate path
$sql = "UPDATE student_profiles 
        SET certificates = ?, updated_at = NOW() 
        WHERE id = ? AND deleted_at IS NULL";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $certificatePath, $studentId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "message" => "Certificate uploaded successfully",
            "status" => true,
            "certificate_path" => $certificatePath
        ]);
    } else {
        echo json_encode([
            "message" => "Certificate already up to date",
            "status" => true,
            "certificate_path" => $certificatePath
        ]);
    }
} else {
    echo json_encode([
        "message" => "Database error: " . $stmt->error,
        "status" => false
    ]);
}

$stmt->close();
$conn->close();
?>
