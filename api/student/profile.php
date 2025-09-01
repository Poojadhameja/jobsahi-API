<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
<<<<<<< HEAD
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate JWT for 'student' role
authenticateJWT('student');  // only students can access
=======
header('Access-Control-Allow-Headers: Content-Type, Authorization');
>>>>>>> 1235f3517c57dd991bcdc278f57123fa99efe289

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ---- Fetch all student profiles ----
$sql = "SELECT 
            id, 
            user_id, 
            skills, 
            education, 
            resume, 
            portfolio_link, 
            linkedin_url, 
            dob, 
            gender, 
            job_type, 
            trade, 
            location, 
            created_at, 
            modified_at, 
            deleted_at
        FROM student_profiles 
        WHERE deleted_at IS NULL 
        ORDER BY created_at DESC";

$result = mysqli_query($conn, $sql);

$students = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($student = mysqli_fetch_assoc($result)) {
        $students[] = $student;
    }
}

echo json_encode([
    "message" => "Student profiles fetched successfully",
    "status" => true,
    "count" => count($students),
    "data" => $students,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
