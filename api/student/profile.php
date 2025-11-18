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
    $sql = "SELECT 
                sp.id, 
                sp.user_id, 
                u.email,
                u.user_name,
                u.phone_number,
                sp.contact_email,
                sp.contact_phone,
                sp.skills, 
                sp.education, 
                sp.resume, 
                sp.certificates,
                sp.socials,
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
                sp.created_at, 
                sp.updated_at, 
                sp.deleted_at
            FROM student_profiles sp
            INNER JOIN users u ON sp.user_id = u.id
            WHERE sp.deleted_at IS NULL 
            ORDER BY sp.created_at DESC";
} else {
    $sql = "SELECT 
                sp.id, 
                sp.user_id, 
                u.email,
                u.user_name,
                u.phone_number,
                sp.contact_email,
                sp.contact_phone,
                sp.skills, 
                sp.education, 
                sp.resume, 
                sp.certificates,
                sp.socials,
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
                sp.created_at, 
                sp.updated_at, 
                sp.deleted_at
            FROM student_profiles sp
            INNER JOIN users u ON sp.user_id = u.id
            WHERE sp.deleted_at IS NULL 
              AND sp.user_id = $logged_in_user_id
            LIMIT 1";
}

$result = mysqli_query($conn, $sql);
$students = [];

if ($result && mysqli_num_rows($result) > 0) {

    // ✅ Base URL setup
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $resume_base = '/jobsahi-API/api/uploads/resume/';
    $cert_base   = '/jobsahi-API/api/uploads/student_certificate/';

    while ($student = mysqli_fetch_assoc($result)) {

        // ✅ Decode Experience - Multiple experiences array
        $experienceData = [];
        if (!empty($student['experience'])) {
            $decoded = json_decode($student['experience'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Check if it's array of experiences or old format
                if (isset($decoded[0]) && is_array($decoded[0])) {
                    // New format: Array of experience objects
                    $experienceData = $decoded;
                } elseif (isset($decoded['level']) || isset($decoded['years'])) {
                    // Old format: {level, years, details}
                    // Convert to new format
                    $experienceData = [];
                    if (!empty($decoded['details']) && is_array($decoded['details'])) {
                        foreach ($decoded['details'] as $detail) {
                            $experienceData[] = [
                                "company_name" => $detail['company'] ?? '',
                                "position" => $detail['position'] ?? '',
                                "start_date" => $detail['start_date'] ?? '',
                                "end_date" => $detail['end_date'] ?? '',
                                "company_location" => $detail['company_location'] ?? '',
                                "description" => $detail['description'] ?? ''
                            ];
                        }
                    }
                } else {
                    // Try to treat as array of experiences
                    $experienceData = $decoded;
                }
            } else {
                // Plain string - convert to empty array
                $experienceData = [];
            }
        }

        // ✅ Decode Projects JSON (if valid)
        $projectsData = [];
        if (!empty($student['projects'])) {
            $decodedProjects = json_decode($student['projects'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedProjects)) {
                $projectsData = $decodedProjects;
            } else {
                $projectsData = [["name" => $student['projects'], "link" => ""]];
            }
        }

        // ✅ Decode Skills - Convert to array format
        $skillsData = [];
        if (!empty($student['skills'])) {
            $decodedSkills = json_decode($student['skills'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedSkills)) {
                // Already JSON array format
                $skillsData = $decodedSkills;
            } else {
                // Convert comma-separated string to array
                $skillsData = array_filter(array_map('trim', explode(',', $student['skills'])));
                $skillsData = array_values($skillsData); // Re-index array
            }
        }

        // ✅ Decode Education - Multiple educations array
        $educationData = [];
        if (!empty($student['education'])) {
            $decodedEducation = json_decode($student['education'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedEducation)) {
                // Check if it's array of educations or old single object format
                if (isset($decodedEducation[0]) && is_array($decodedEducation[0])) {
                    // New format: Array of education objects
                    $educationData = $decodedEducation;
                } elseif (isset($decodedEducation['course_name']) || isset($decodedEducation['college_name'])) {
                    // Old format: Single education object - convert to array
                    $educationData = [$decodedEducation];
                } else {
                    // Try to treat as array
                    $educationData = $decodedEducation;
                }
            } else {
                // Plain text - convert to old format then to new
                $educationData = [[
                    "qualification" => $student['education'],
                    "institute" => "",
                    "start_year" => "",
                    "end_year" => !empty($student['graduation_year']) ? strval($student['graduation_year']) : "",
                    "is_pursuing" => false,
                    "pursuing_year" => null,
                    "cgpa" => !empty($student['cgpa']) ? floatval($student['cgpa']) : null
                ]];
            }
        }
        // Normalize education array structure
        foreach ($educationData as &$edu) {
            if (!isset($edu['qualification'])) $edu['qualification'] = $edu['course_name'] ?? '';
            if (!isset($edu['institute'])) $edu['institute'] = $edu['college_name'] ?? '';
            if (!isset($edu['start_year'])) $edu['start_year'] = $edu['start_date'] ?? '';
            if (!isset($edu['end_year'])) $edu['end_year'] = $edu['end_date'] ?? $edu['graduation_year'] ?? '';
            if (!isset($edu['is_pursuing'])) $edu['is_pursuing'] = false;
            if (!isset($edu['pursuing_year'])) $edu['pursuing_year'] = null;
            if (!isset($edu['cgpa'])) $edu['cgpa'] = null;
        }

        // ✅ Decode Languages - Convert to array format (spoken languages)
        $languagesData = [];
        if (!empty($student['languages'])) {
            $decodedLanguages = json_decode($student['languages'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedLanguages)) {
                // Already JSON array format
                $languagesData = $decodedLanguages;
            } else {
                // Convert comma-separated string to array
                $languagesData = array_filter(array_map('trim', explode(',', $student['languages'])));
                $languagesData = array_values($languagesData); // Re-index array
            }
        }

        // ✅ Decode Social Links - Multiple social links array from socials field
        $socialLinksData = [];
        if (!empty($student['socials'])) {
            $decodedSocials = json_decode($student['socials'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedSocials)) {
                // Already JSON array format
                $socialLinksData = $decodedSocials;
            }
        }

        // ✅ Normalize empty/null fields
        foreach ([
            'resume', 'certificates', 'dob', 'gender', 'job_type', 'trade', 'location',
            'bio', 'aadhar_number'
        ] as $field) {
            if (!isset($student[$field]) || $student[$field] === null) {
                $student[$field] = "";
            }
        }

        // ✅ Structured formatted response
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
            "contact_info" => [
                "contact_email" => $student['contact_email'] ?? null,
                "contact_phone" => $student['contact_phone'] ?? null
            ],
            "professional_info" => [
                "skills" => $skillsData,  // ✅ Array format: ["PHP", "HTML", "CSS"]
                "education" => $educationData,  // ✅ Array of education objects
                "experience" => $experienceData,  // ✅ Array of experience objects
                "projects" => $projectsData,
                "job_type" => $student['job_type'],
                "trade" => $student['trade'],
                "languages" => $languagesData  // ✅ Array format: ["Hindi", "English", "Gujarati"]
            ],
            "documents" => [
                "resume" => $student['resume'],
                "certificates" => $student['certificates'],
                "aadhar_number" => $student['aadhar_number']
            ],
            "social_links" => $socialLinksData,  // ✅ Array of social link objects
            "additional_info" => [
                "bio" => $student['bio']
            ],
            "status" => [
                "created_at" => $student['created_at'],
                "modified_at" => $student['updated_at'],
                "deleted_at" => $student['deleted_at']
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
                "deleted_at" => "NULL",
                "scope" => $user_role === 'admin' ? 'all_profiles' : 'self'
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
                "deleted_at" => "NULL",
                "scope" => $user_role === 'admin' ? 'all_profiles' : 'self'
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
