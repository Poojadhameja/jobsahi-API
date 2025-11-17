<?php 
// list_students.php - List/manage all students with applied job titles + courses + placement status
require_once '../cors.php';
require_once '../db.php';

// Authenticate
$decoded = authenticateJWT(['admin', 'institute']);
$admin_id = $decoded['user_id'] ?? null;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    /* =========================================================
        📊 1️⃣ Dashboard Summary Counts
    ========================================================= */
    $summary = [
        "total_students" => 0,
        "verified_profiles" => 0,
        "placement_ready" => 0,
        "successfully_placed" => 0
    ];

    // Total students
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role='student'");
    $stmt->execute();
    $summary['total_students'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Verified profiles
    $stmt = $conn->prepare("SELECT COUNT(*) AS verified FROM users WHERE role='student' AND is_verified = 1");
    $stmt->execute();
    $summary['verified_profiles'] = $stmt->get_result()->fetch_assoc()['verified'] ?? 0;
    $stmt->close();

    // Placement ready
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sp.user_id) AS placement_ready
        FROM student_profiles sp
        INNER JOIN applications a ON a.student_id = sp.id
        WHERE a.status IN ('shortlisted','selected') 
          AND a.deleted_at IS NULL
    ");
    $stmt->execute();
    $summary['placement_ready'] = $stmt->get_result()->fetch_assoc()['placement_ready'] ?? 0;
    $stmt->close();

    // Successfully placed (selected)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sp.user_id) AS placed
        FROM student_profiles sp
        INNER JOIN applications a ON a.student_id = sp.id
        WHERE a.status = 'selected' 
          AND a.deleted_at IS NULL
    ");
    $stmt->execute();
    $summary['successfully_placed'] = $stmt->get_result()->fetch_assoc()['placed'] ?? 0;
    $stmt->close();


    /* =========================================================
        2️⃣ Fetch Student Basic Details (existing logic)
    ========================================================= */
    $stmt = $conn->prepare("
        SELECT 
            u.id AS user_id,
            u.user_name,
            u.email,
            u.phone_number,
            u.status AS user_status,
            sp.id AS profile_id,
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
            sp.bio,
            sp.experience,
            sp.graduation_year,
            sp.cgpa,
            sp.created_at AS profile_created_at,
            sp.updated_at AS profile_modified_at,
            sp.deleted_at AS profile_deleted_at
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.role = 'student'
        ORDER BY u.id DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    // File URL template
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $resume_folder = '/jobsahi-API/api/uploads/resume/';
    $certificate_folder = '/jobsahi-API/api/uploads/student_certificate/';

    $students = [];
    $student_profile_ids = []; // student_profiles.id list

    while ($row = $result->fetch_assoc()) {

        $resume_url = !empty($row['resume']) ? $protocol . $host . $resume_folder . basename($row['resume']) : null;
        $certificate_url = !empty($row['certificates']) ? $protocol . $host . $certificate_folder . basename($row['certificates']) : null;

        $students[$row['profile_id']] = [   // KEY = profile_id (student_profiles.id)
            'user_info' => [
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
                'status' => ucfirst($row['user_status'] ?? 'Inactive'),
            ],
            'profile_info' => [
                'profile_id' => $row['profile_id'],
                'skills' => $row['skills'],
                'education' => $row['education'],
                'resume' => $resume_url,
                'certificates' => $certificate_url,
                'portfolio_link' => $row['portfolio_link'],
                'linkedin_url' => $row['linkedin_url'],
                'dob' => $row['dob'],
                'gender' => $row['gender'],
                'job_type' => $row['job_type'],
                'trade' => $row['trade'],
                'location' => $row['location'],
                'bio' => $row['bio'],
                'experience' => $row['experience'],
                'graduation_year' => $row['graduation_year'],
                'cgpa' => $row['cgpa'],
                'created_at' => $row['profile_created_at'],
                'modified_at' => $row['profile_modified_at'],
                'deleted_at' => $row['profile_deleted_at']
            ],

            // placeholders to be filled
            'courses' => [],
            'applied_jobs' => [],
            'placement_status' => "Not Applied"
        ];

        if ($row['profile_id']) {
            $student_profile_ids[] = $row['profile_id'];
        }
    }
    $stmt->close();


    /* =========================================================
        3️⃣ Fetch Applied Jobs (job title + status)
    ========================================================= */
    if (!empty($student_profile_ids)) {

        $ids = implode(",", $student_profile_ids);

     $jobStmt = $conn->prepare("
    SELECT 
        a.student_id,
        j.title AS job_title,
        rp.company_name AS company_name,   -- 🔥 NEW FIELD
        a.status AS placement_status,
        a.job_selected
    FROM applications a
    INNER JOIN jobs j ON j.id = a.job_id
    LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id   -- 🔥 JOIN ADDED
    WHERE a.student_id IN ($ids)
      AND a.deleted_at IS NULL
");

        $jobStmt->execute();
        $jobResult = $jobStmt->get_result();

        while ($job = $jobResult->fetch_assoc()) {

           $students[$job['student_id']]['applied_jobs'][] = [
    "job_title" => $job['job_title'],
    "company_name" => $job['company_name'],  // 🔥 COMPANY ADDED
    "status" => $job['placement_status'],
    "job_selected" => $job['job_selected']
];


            // Auto placement label
            if ($job['placement_status'] == 'selected') {
                $students[$job['student_id']]['placement_status'] = "Selected";
            } 
            elseif ($job['placement_status'] == 'shortlisted') {
                $students[$job['student_id']]['placement_status'] = "Shortlisted";
            } 
            elseif ($job['placement_status'] == 'applied') {
                $students[$job['student_id']]['placement_status'] = "Applied";
            }
        }

        $jobStmt->close();
    }


    /* =========================================================
        4️⃣ Fetch enrolled courses for each student
        👉 Same course repeat nahi hoga (per student)
    ========================================================= */
    $courseStmt = $conn->prepare("
        SELECT 
            sce.student_id,
            sce.course_id,
            c.title AS course_name,
            sce.status AS course_status
        FROM student_course_enrollments sce
        INNER JOIN courses c ON c.id = sce.course_id
        WHERE sce.deleted_at IS NULL
    ");
    $courseStmt->execute();
    $courseRes = $courseStmt->get_result();

    // helper array to avoid duplicate same course for same student
    $studentCourses = []; // [student_id][course_id] = true

    while ($cr = $courseRes->fetch_assoc()) {

        $sid = (int)$cr['student_id'];   // student_profiles.id
        $cid = (int)$cr['course_id'];    // courses.id

        if (!isset($students[$sid])) {
            continue; // in case profile not in list
        }

        if (!isset($studentCourses[$sid])) {
            $studentCourses[$sid] = [];
        }

        // if this student already has this course added → skip (avoid loop duplicates)
        if (isset($studentCourses[$sid][$cid])) {
            continue;
        }

        // mark as seen
        $studentCourses[$sid][$cid] = true;

        // push unique course entry
        $students[$sid]['courses'][] = [
            "course_name" => $cr['course_name'],
            "course_status" => $cr['course_status']
        ];
    }

    $courseStmt->close();


    /* =========================================================
        5️⃣ Final Response
    ========================================================= */
    echo json_encode([
        "status" => true,
        "message" => "Students retrieved successfully",
        "summary" => $summary,
        "count" => count($students),
        "data" => array_values($students)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
