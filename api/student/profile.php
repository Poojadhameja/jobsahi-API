<?php
require_once '../cors.php';

// ✅ Authenticate JWT (allowed roles: admin, student)
$current_user = authenticateJWT(['admin', 'student']); 
$user_role = strtolower($current_user['role']);
$logged_in_user_id = intval($current_user['user_id']); // from JWT

// ✅ Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

// ✅ Role-based SQL condition
if ($user_role === 'admin') {
    // Admin: can view all profiles (pending + approved)
    $sql = "SELECT 
                id, 
                user_id, 
                skills, 
                education, 
                resume, 
                certificates,
                portfolio_link, 
                linkedin_url, 
                dob, 
                gender, 
                job_type, 
                trade, 
                location, 
                bio,
                experience,
                graduation_year,
                cgpa,
                admin_action,
                created_at, 
                modified_at, 
                deleted_at
            FROM student_profiles 
            WHERE deleted_at IS NULL 
              AND (admin_action = 'pending' OR admin_action = 'approved')
            ORDER BY created_at DESC";
} else {
    // Student: can view ONLY their own profile AND only if approved
    $sql = "SELECT 
                id, 
                user_id, 
                skills, 
                education, 
                resume, 
                certificates,
                portfolio_link, 
                linkedin_url, 
                dob, 
                gender, 
                job_type, 
                trade, 
                location, 
                bio,
                experience,
                graduation_year,
                cgpa,
                admin_action,
                created_at, 
                modified_at, 
                deleted_at
            FROM student_profiles 
            WHERE deleted_at IS NULL 
              AND user_id = $logged_in_user_id
              AND admin_action = 'approved'
            LIMIT 1"; // Each student should have only one profile
}

$result = mysqli_query($conn, $sql);
$students = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($student = mysqli_fetch_assoc($result)) {
        // ✅ Process experience field
        $experienceData = null;
        
        if (!empty($student['experience'])) {
            $decoded = json_decode($student['experience'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $experienceData = $decoded;
            } else {
                // Not valid JSON → fallback
                $experienceData = [
                    "level" => "",
                    "years" => $student['experience'],
                    "details" => []
                ];
            }
        } else {
            // Empty experience
            $experienceData = [
                "level" => "",
                "years" => "",
                "details" => []
            ];
        }
        
        $student['experience'] = $experienceData;
        $students[] = $student;
    }

    echo json_encode([
        "message" => "Student profiles fetched successfully",
        "status" => true,
        "count" => count($students),
        "data" => $students,
        "timestamp" => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        "message" => $user_role === 'student' 
            ? "No profile found for this student" 
            : "No student profiles found",
        "status" => false,
        "count" => 0,
        "data" => [],
        "timestamp" => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}

mysqli_close($conn);
?>