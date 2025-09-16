<?php
include '../CORS.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT (allowed roles: admin, student)
$current_user = authenticateJWT(['admin', 'student']); 
$user_role = strtolower($current_user['role']); // assuming payload has 'role'

// ✅ Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ✅ Role-based SQL condition
if ($user_role === 'admin') {
    // Admin sees both pending and approved
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
                admin_action,
                created_at, 
                modified_at, 
                deleted_at
            FROM student_profiles 
            WHERE deleted_at IS NULL 
              AND (admin_action = 'pending' OR admin_action = 'approved')
            ORDER BY created_at DESC";
} else {
    // Other roles → only approved
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
                admin_action,
                created_at, 
                modified_at, 
                deleted_at
            FROM student_profiles 
            WHERE deleted_at IS NULL 
              AND admin_action = 'approved'
            ORDER BY created_at DESC";
}

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
