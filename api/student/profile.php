<?php
require_once '../cors.php';
require_once '../db.php'; // ✅ ensure DB connection

// ✅ Authenticate JWT (allowed roles: admin, student)
$current_user = authenticateJWT(['admin', 'student']); 
$user_role = strtolower($current_user['role']);
$logged_in_user_id = intval($current_user['user_id']); // from JWT

// ✅ Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

// ✅ Build SQL based on role
if ($user_role === 'admin') {
    // Admin can view all profiles (pending + approved)
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
                latitude,
                longitude,
                bio,
                experience,
                projects,
                languages,
                aadhar_number,
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
    // Student can only view their own approved profile
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
                latitude,
                longitude,
                bio,
                experience,
                projects,
                languages,
                aadhar_number,
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
            LIMIT 1";
}

$result = mysqli_query($conn, $sql);
$students = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($student = mysqli_fetch_assoc($result)) {

        // ✅ Decode Experience JSON (if valid)
        $experienceData = [];
        if (!empty($student['experience'])) {
            $decoded = json_decode($student['experience'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $experienceData = $decoded;
            } else {
                $experienceData = [
                    "level" => "",
                    "years" => $student['experience'],
                    "details" => []
                ];
            }
        } else {
            $experienceData = [
                "level" => "",
                "years" => "",
                "details" => []
            ];
        }

        // ✅ Decode Projects JSON (if valid)
        $projectsData = [];
        if (!empty($student['projects'])) {
            $decodedProjects = json_decode($student['projects'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedProjects)) {
                // ✅ Projects array (e.g. [{name:"",link:""}])
                $projectsData = $decodedProjects;
            } else {
                // fallback if plain string
                $projectsData = [["name" => $student['projects'], "link" => ""]];
            }
        }

        // ✅ Normalize empty/null fields
        foreach ([
            'skills', 'education', 'resume', 'certificates', 'portfolio_link',
            'linkedin_url', 'dob', 'gender', 'job_type', 'trade', 'location',
            'bio', 'languages', 'aadhar_number', 'graduation_year', 'cgpa'
        ] as $field) {
            if (!isset($student[$field]) || $student[$field] === null) {
                $student[$field] = "";
            }
        }

        // ✅ Replace with decoded structured data
        $student['experience'] = $experienceData;
        $student['projects'] = $projectsData;

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
