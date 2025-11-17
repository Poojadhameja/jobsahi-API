<?php
// job-detail.php - Single Job Detail API
require_once '../cors.php';

// ✅ Authenticate roles (students, recruiters, admins)
$decodedToken = authenticateJWT(['student', 'recruiter', 'admin']);
$user_role = $decodedToken['role']; // role from JWT

// Get student_id if user is student
$student_profile_id = null;
if ($user_role === 'student') {
    $user_id = $decodedToken['id'] ?? $decodedToken['user_id'] ?? $decodedToken['student_id'] ?? null;
    if ($user_id) {
        $check_student_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
        $check_student_stmt = mysqli_prepare($conn, $check_student_sql);
        mysqli_stmt_bind_param($check_student_stmt, "i", $user_id);
        mysqli_stmt_execute($check_student_stmt);
        $student_result = mysqli_stmt_get_result($check_student_stmt);
        if (mysqli_num_rows($student_result) > 0) {
            $student_profile = mysqli_fetch_assoc($student_result);
            $student_profile_id = $student_profile['id'];
        }
        mysqli_stmt_close($check_student_stmt);
    }
}

// ✅ Validate job ID
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($job_id <= 0) {
    echo json_encode(["message" => "Valid job ID is required", "status" => false]);
    mysqli_close($conn);
    exit;
}

// ✅ Set visibility condition
$visibilityCondition = ($user_role === 'admin') 
    ? "j.admin_action IN ('pending', 'approved')" 
    : "j.admin_action = 'approved'";

// ✅ Main Query (added recruiter_company_info join + job_category join)
$sql = "SELECT 
            j.id,
            j.recruiter_id,
            j.category_id,
            j.title,
            j.description,
            j.location,
            j.skills_required,
            j.salary_min,
            j.salary_max,
            j.job_type,
            j.experience_required,
            j.application_deadline,
            j.is_remote,
            j.no_of_vacancies,
            j.status,
            j.admin_action,
            j.created_at,

            -- Category info
            jc.category_name,

            -- Recruiter information
            rp.company_name,
            rp.company_logo,
            rp.industry,
            rp.website,
            rp.location AS company_location,

            -- ✅ Contact info from recruiter_company_info
            rci.person_name,
            rci.phone,
            rci.additional_contact,

            -- Job statistics
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS total_views,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS total_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'applied') AS pending_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'shortlisted') AS shortlisted_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'selected') AS selected_applications,
            (SELECT COUNT(*) FROM saved_jobs sj WHERE sj.job_id = j.id) AS times_saved";

if ($user_role === 'student' && $student_profile_id) {
    $sql .= ",
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM saved_jobs sj 
                    WHERE sj.job_id = j.id AND sj.student_id = ?
                ) THEN 1 
                ELSE 0 
            END as is_saved,
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM applications a 
                    WHERE a.job_id = j.id AND a.student_id = ?
                    AND (a.deleted_at IS NULL OR a.deleted_at = '0000-00-00 00:00:00')
                ) THEN 1 
                ELSE 0 
            END as is_applied";
}

$sql .= " FROM jobs j
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        LEFT JOIN recruiter_company_info rci ON rci.job_id = j.id
        LEFT JOIN job_category jc ON j.category_id = jc.id
        WHERE j.id = ? AND $visibilityCondition";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    mysqli_close($conn);
    exit;
}

if ($user_role === 'student' && $student_profile_id) {
    // Bind student_id twice: once for is_saved, once for is_applied
    mysqli_stmt_bind_param($stmt, "iii", $student_profile_id, $student_profile_id, $job_id);
} else {
    mysqli_stmt_bind_param($stmt, "i", $job_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    echo json_encode(["message" => "Job not found or not accessible", "status" => false]);
    exit;
}

$job = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// ✅ Format Response
$formatted_job = [
    'job_info' => [
        'id' => intval($job['id']),
        'title' => $job['title'],
        'category_id' => intval($job['category_id']),
        'category_name' => $job['category_name'] ?? '', // ✅ Added category name
        'description' => $job['description'],
        'location' => $job['location'],
        'skills_required' => !empty($job['skills_required']) ? array_map('trim', explode(',', $job['skills_required'])) : [],
        'salary_min' => floatval($job['salary_min']),
        'salary_max' => floatval($job['salary_max']),
        'job_type' => $job['job_type'],
        'experience_required' => $job['experience_required'],
        'application_deadline' => $job['application_deadline'],
        'is_remote' => (bool)$job['is_remote'],
        'no_of_vacancies' => intval($job['no_of_vacancies']),
        'status' => $job['status'],
        'admin_action' => $job['admin_action'],
        'created_at' => $job['created_at'],

        // ✅ Newly added contact info
        'person_name' => $job['person_name'] ?? '',
        'phone' => $job['phone'] ?? '',
        'additional_contact' => $job['additional_contact'] ?? '',
        
        // ✅ Student-specific flags (return as 1 or 0 like jobs.php for consistency)
        'is_saved' => isset($job['is_saved']) ? (int)$job['is_saved'] : 0,
        'is_applied' => isset($job['is_applied']) ? (int)$job['is_applied'] : 0,
    ],
    'company_info' => [
        'recruiter_id' => intval($job['recruiter_id']),
        'company_name' => $job['company_name'],
        'company_logo' => $job['company_logo'],
        'industry' => $job['industry'],
        'website' => $job['website'],
        'location' => $job['company_location']
    ],
    'statistics' => [
        'total_views' => intval($job['total_views']),
        'total_applications' => intval($job['total_applications']),
        'pending_applications' => intval($job['pending_applications']),
        'shortlisted_applications' => intval($job['shortlisted_applications']),
        'selected_applications' => intval($job['selected_applications']),
        'times_saved' => intval($job['times_saved'])
    ]
];

// ✅ Similar jobs (unchanged)
if (isset($_GET['include_similar']) && $_GET['include_similar'] === 'true') {
    $similarVisibilityCondition = $visibilityCondition;
    $similar_sql = "SELECT 
                        j.id,
                        j.title,
                        j.location,
                        j.salary_min,
                        j.salary_max,
                        j.job_type,
                        rp.company_name
                    FROM jobs j
                    LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
                    WHERE j.id != ? 
                    AND j.status = 'open'
                    AND $similarVisibilityCondition
                    AND (j.location = ? OR j.job_type = ?)
                    ORDER BY j.created_at DESC
                    LIMIT 5";
    $similar_stmt = mysqli_prepare($conn, $similar_sql);
    if ($similar_stmt) {
        mysqli_stmt_bind_param($similar_stmt, "iss", $job_id, $job['location'], $job['job_type']);
        mysqli_stmt_execute($similar_stmt);
        $similar_result = mysqli_stmt_get_result($similar_stmt);
        $similar_jobs = [];
        while ($row = mysqli_fetch_assoc($similar_result)) {
            $similar_jobs[] = [
                'id' => intval($row['id']),
                'title' => $row['title'],
                'location' => $row['location'],
                'salary_min' => floatval($row['salary_min']),
                'salary_max' => floatval($row['salary_max']),
                'job_type' => $row['job_type'],
                'company_name' => $row['company_name']
            ];
        }
        $formatted_job['similar_jobs'] = $similar_jobs;
        mysqli_stmt_close($similar_stmt);
    }
}

mysqli_close($conn);

// ✅ Final response
echo json_encode([
    "message" => "Job details fetched successfully",
    "status" => true,
    "data" => $formatted_job,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
