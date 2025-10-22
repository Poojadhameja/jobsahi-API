<?php
require_once '../cors.php';

// ✅ Authenticate JWT (allowed roles: admin, student)
$current_user = authenticateJWT(['admin', 'student']);
$user_role = strtolower($current_user['role']);
$user_id = $current_user['user_id']; // ✅ user_id from token

// ✅ Allow only PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode(["message" => "Only PUT requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ✅ Determine student_id based on role
if ($user_role === 'admin') {
    // Admin can update any student's profile
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
    if ($student_id <= 0) {
        echo json_encode(["message" => "Missing or invalid student_id for admin", "status" => false]);
        exit;
    }
} else {
    // Student can only update their own profile
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($student_id);
    $stmt->fetch();
    $stmt->close();

    if (!$student_id) {
        echo json_encode(["message" => "Profile not found for this student", "status" => false]);
        exit;
    }
}

// ✅ Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["message" => "Invalid JSON input", "status" => false]);
    exit;
}

// ✅ Extract fields from input
$skills = $input['skills'] ?? null;
$education = $input['education'] ?? null;
$resume = $input['resume'] ?? null;
$certificates = $input['certificates'] ?? null;
$portfolio_link = $input['portfolio_link'] ?? null;
$linkedin_url = $input['linkedin_url'] ?? null;
$dob = $input['dob'] ?? null;
$gender = $input['gender'] ?? null;
$job_type = $input['job_type'] ?? null;
$trade = $input['trade'] ?? null;
$location = $input['location'] ?? null;
$bio = $input['bio'] ?? null;
$graduation_year = $input['graduation_year'] ?? null;
$cgpa = $input['cgpa'] ?? null;

// ✅ Process experience field - convert to JSON string
$experience = null;

if (isset($input['experience'])) {
    if (is_array($input['experience'])) {
        // ✅ Check if already has the correct structure
        if (isset($input['experience']['level']) && isset($input['experience']['years']) && isset($input['experience']['details'])) {
            // Already properly structured
            $experience = json_encode($input['experience'], JSON_UNESCAPED_UNICODE);
        } else {
            // ✅ Extract level and years from input, or use defaults
            $level = $input['level'] ?? 'experience';
            $years = $input['years'] ?? '';
            
            // Assume the array is the details
            $experienceData = [
                "level" => $level,
                "years" => $years,
                "details" => $input['experience']
            ];
            $experience = json_encode($experienceData, JSON_UNESCAPED_UNICODE);
        }
    } elseif (is_string($input['experience'])) {
        // ✅ If a string is sent, check if it's valid JSON
        $decoded = json_decode($input['experience'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Valid JSON - check if already structured
            if (isset($decoded['level']) && isset($decoded['years']) && isset($decoded['details'])) {
                // Already structured properly
                $experience = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            } else {
                // Wrap decoded data inside structured format
                $level = $input['level'] ?? 'experience';
                $years = $input['years'] ?? '';
                $experienceData = [
                    "level" => $level,
                    "years" => $years,
                    "details" => $decoded
                ];
                $experience = json_encode($experienceData, JSON_UNESCAPED_UNICODE);
            }
        } else {
            // ✅ Plain text fallback
            $experienceData = [
                "level" => "experience",
                "years" => $input['experience'],
                "details" => []
            ];
            $experience = json_encode($experienceData, JSON_UNESCAPED_UNICODE);
        }
    }
}

// ✅ Build UPDATE query
$sql = "UPDATE student_profiles SET 
            skills = ?, 
            education = ?, 
            resume = ?, 
            certificates = ?,
            portfolio_link = ?, 
            linkedin_url = ?,
            dob = ?, 
            gender = ?, 
            job_type = ?, 
            trade = ?, 
            location = ?,
            bio = ?,
            experience = ?,
            graduation_year = ?,
            cgpa = ?,
            modified_at = NOW()
        WHERE id = ? AND deleted_at IS NULL";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "message" => "Failed to prepare statement: " . mysqli_error($conn),
        "status" => false
    ]);
    mysqli_close($conn);
    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    "sssssssssssssssi",
    $skills,
    $education,
    $resume,
    $certificates,
    $portfolio_link,
    $linkedin_url,
    $dob,
    $gender,
    $job_type,
    $trade,
    $location,
    $bio,
    $experience,
    $graduation_year,
    $cgpa,
    $student_id
);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            "message" => "Student profile updated successfully",
            "status" => true,
            "profile_updated_id" => $student_id,
            "updated_by" => $user_role,
            "timestamp" => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "message" => "No record updated. Check student_id or profile may be deleted",
            "status" => false
        ]);
    }
} else {
    echo json_encode([
        "message" => "Update failed: " . mysqli_stmt_error($stmt),
        "status" => false
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>