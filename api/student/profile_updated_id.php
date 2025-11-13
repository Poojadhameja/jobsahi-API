<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';  // Include your JWT helper
require_once '../auth/auth_middleware.php';  // Include your middleware

// ✅ Authenticate and allow 'admin' and 'student' roles
$decoded_token = authenticateJWT(['admin', 'student']);

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["message" => "Only PUT requests allowed", "status" => false]);
    exit;
}

include "../db.php";

// ✅ Extract student ID from URL - supports both path and query parameters
$id = null;

if (isset($_GET['student_id'])) {
    $id = intval($_GET['student_id']);
} else {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path_parts = explode('/', trim($request_uri, '/'));

    for ($i = 0; $i < count($path_parts); $i++) {
        if ($path_parts[$i] === 'profile' && isset($path_parts[$i + 1]) && is_numeric($path_parts[$i + 1])) {
            $id = intval($path_parts[$i + 1]);
            break;
        }
    }
}

if (!$id) {
    echo json_encode(["message" => "Student ID required in URL path or query parameter", "status" => false]);
    exit;
}

// ✅ Get student ID from JWT token
$jwt_student_id = $decoded_token['user_id'] ?? $decoded_token['student_id'] ?? null;

// ✅ Restrict student to update only their own profile
if ($decoded_token['role'] === 'student' && $jwt_student_id != $id) {
    echo json_encode(["message" => "You can only update your own profile", "status" => false]);
    exit;
}

// ✅ Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(["message" => "Invalid JSON input", "status" => false]);
    exit;
}

// =============================================================
// ✅ Prepare fields
// =============================================================
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

// =============================================================
// ✅ Build Resume Full URL (like other APIs)
// =============================================================
$resume_url = null;
if (!empty($resume)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $resume_folder = '/jobsahi-API/api/uploads/resume/';

    $clean_resume = str_replace(["\\", "/uploads/resume/", "./", "../"], "", $resume);
    $resume_local = __DIR__ . '/../uploads/resume/' . $clean_resume;

    if (file_exists($resume_local)) {
        $resume_url = $protocol . $host . $resume_folder . $clean_resume;
    } else {
        $resume_url = $resume; // fallback if file missing or external URL
    }
}

// =============================================================
// ✅ Update Query
// =============================================================
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
    $resume_url, // ✅ Use full URL
    $portfolio_link,
    $linkedin_url,
    $dob,
    $gender,
    $job_type,
    $trade,
    $location,
    $id
);

// =============================================================
// ✅ Execute and respond
// =============================================================
if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            "message" => "Student profile updated successfully",
            "status" => true,
            "resume_url" => $resume_url // ✅ Added resume URL in response
        ]);
    } else {
        echo json_encode([
            "message" => "No record updated. Check ID or record may be deleted",
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
