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
                sp.id, 
                sp.user_id, 
                u.email,
                u.user_name,
                u.phone_number,
                sp.skills, 
                sp.education, 
                sp.resume, 
                sp.certificates,
                sp.portfolio_link, 
                sp.linkedin_url, 
                sp.dob, 
                sp.gender, 
                sp.job_type, 
                sp.trade, 
                sp.location, 
                sp.latitude,
                sp.longitude,
                sp.bio,
                sp.experience,
                sp.projects,
                sp.languages,
                sp.aadhar_number,
                sp.graduation_year,
                sp.cgpa,
                sp.admin_action,
                sp.created_at, 
                sp.updated_at, 
                sp.deleted_at
            FROM student_profiles sp
            INNER JOIN users u ON sp.user_id = u.id
            WHERE sp.deleted_at IS NULL 
              AND (sp.admin_action = 'pending' OR sp.admin_action = 'approved')
            ORDER BY sp.created_at DESC";
} else {
    // Student can only view their own approved profile
    $sql = "SELECT 
                sp.id, 
                sp.user_id, 
                u.email,
                u.user_name,
                u.phone_number,
                sp.skills, 
                sp.education, 
                sp.resume, 
                sp.certificates,
                sp.portfolio_link, 
                sp.linkedin_url, 
                sp.dob, 
                sp.gender, 
                sp.job_type, 
                sp.trade, 
                sp.location, 
                sp.latitude,
                sp.longitude,
                sp.bio,
                sp.experience,
                sp.projects,
                sp.languages,
                sp.aadhar_number,
                sp.graduation_year,
                sp.cgpa,
                sp.admin_action,
                sp.created_at, 
                sp.updated_at, 
                sp.deleted_at
            FROM student_profiles sp
            INNER JOIN users u ON sp.user_id = u.id
            WHERE sp.deleted_at IS NULL 
              AND sp.user_id = $logged_in_user_id
              AND sp.admin_action = 'approved'
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

        // ✅ Format structured response
        $formattedStudent = [
            "profile_id" => intval($student['id']),
            "user_id" => intval($student['user_id']),
            "personal_info" => [
                "email" => $student['email'],
                "user_name" => $student['user_name'],
                "phone_number" => $student['phone_number'],
                "date_of_birth" => $student['dob'],
                "gender" => $student['gender'],
                "location" => $student['location'],
                "latitude" => $student['latitude'] ? floatval($student['latitude']) : null,
                "longitude" => $student['longitude'] ? floatval($student['longitude']) : null
            ],
            "professional_info" => [
                "skills" => $student['skills'],
                "education" => $student['education'],
                "experience" => $experienceData,
                "projects" => $projectsData,
                "job_type" => $student['job_type'],
                "trade" => $student['trade'],
                "graduation_year" => $student['graduation_year'],
                "cgpa" => $student['cgpa'],
                "languages" => $student['languages']
            ],
            "documents" => [
                "resume" => $student['resume'],
                "certificates" => $student['certificates'],
                "aadhar_number" => $student['aadhar_number']
            ],
            "social_links" => [
                "portfolio_link" => $student['portfolio_link'],
                "linkedin_url" => $student['linkedin_url']
            ],
            "additional_info" => [
                "bio" => $student['bio']
            ],
            "status" => [
                "admin_action" => $student['admin_action'],
                "created_at" => $student['created_at'],
                "modified_at" => $student['modified_at']
            ]
        ];

        $students[] = $formattedStudent;
    }

    echo json_encode([
        "success" => true,
        "message" => "Student profiles retrieved successfully",
        "data" => [
            "profiles" => $students,
            "total_count" => count($students),
            "user_role" => $user_role,
            "filters_applied" => [
                "admin_action" => $user_role === 'admin' ? ['pending', 'approved'] : ['approved'],
                "deleted_at" => "NULL"
            ]
        ],
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0",
            "response_format" => "structured"
        ]
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        "success" => false,
        "message" => $user_role === 'student' 
            ? "No profile found for this student" 
            : "No student profiles found",
        "data" => [
            "profiles" => [],
            "total_count" => 0,
            "user_role" => $user_role,
            "filters_applied" => [
                "admin_action" => $user_role === 'admin' ? ['pending', 'approved'] : ['approved'],
                "deleted_at" => "NULL"
            ]
        ],
        "meta" => [
            "timestamp" => date('Y-m-d H:i:s'),
            "api_version" => "1.0",
            "response_format" => "structured"
        ]
    ], JSON_PRETTY_PRINT);
}

mysqli_close($conn);
?>
