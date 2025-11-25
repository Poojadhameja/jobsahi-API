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

// ❌ REMOVE admin_action from visibility
$visibilityCondition = "j.status = 'open'";

// (Admin sees open or closed)
if ($user_role === 'admin') {
    $visibilityCondition = "j.status IN ('open','closed')";
}

// ✅ Main Query (admin_action removed)
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
            j.created_at,

            jc.category_name,

            rp.company_name,
            rp.company_logo,
            rp.industry,
            rp.website,
            rp.location AS company_location,

            rci.person_name,
            rci.phone,
            rci.additional_contact,

            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS total_views,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS total_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'applied') AS pending_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'shortlisted') AS shortlisted_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'selected') AS selected_applications,
            (SELECT COUNT(*) FROM saved_jobs sj WHERE sj.job_id = j.id) AS times_saved";

if ($user_role === 'student' && $student_profile_id) {
    $sql .= ",
        CASE WHEN EXISTS (
            SELECT 1 FROM saved_jobs sj WHERE sj.job_id = j.id AND sj.student_id = ?
        ) THEN 1 ELSE 0 END as is_saved,

        CASE WHEN EXISTS (
            SELECT 1 FROM applications a 
            WHERE a.job_id = j.id AND a.student_id = ?
        ) THEN 1 ELSE 0 END as is_applied";
}

$sql .= " FROM jobs j
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        LEFT JOIN recruiter_company_info rci ON rci.job_id = j.id
        LEFT JOIN job_category jc ON j.category_id = jc.id
        WHERE j.id = ? AND $visibilityCondition";

$stmt = mysqli_prepare($conn, $sql);

if ($user_role === 'student' && $student_profile_id) {
    mysqli_stmt_bind_param($stmt, "iii", $student_profile_id, $student_profile_id, $job_id);
} else {
    mysqli_stmt_bind_param($stmt, "i", $job_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(["message" => "Job not found or not accessible", "status" => false]);
    exit;
}

$job = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Add full company logo URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$logo_base = '/jobsahi-API/api/uploads/recruiter_logo/';

if (!empty($job['company_logo'])) {
    $clean = basename($job['company_logo']);
    $job['company_logo'] = $protocol . $host . $logo_base . $clean;
}

// Format Response
$formatted_job = [
    'job_info' => [
        'id' => intval($job['id']),
        'title' => $job['title'],
        'category_id' => intval($job['category_id']),
        'category_name' => $job['category_name'],
        'description' => $job['description'],
        'location' => $job['location'],
        'skills_required' => array_map('trim', explode(',', $job['skills_required'])),
        'salary_min' => floatval($job['salary_min']),
        'salary_max' => floatval($job['salary_max']),
        'job_type' => $job['job_type'],
        'experience_required' => $job['experience_required'],
        'application_deadline' => $job['application_deadline'],
        'is_remote' => (bool)$job['is_remote'],
        'no_of_vacancies' => intval($job['no_of_vacancies']),
        'status' => $job['status'],
        'created_at' => $job['created_at'],

        'person_name' => $job['person_name'],
        'phone' => $job['phone'],
        'additional_contact' => $job['additional_contact'],

        'is_saved' => $job['is_saved'] ?? 0,
        'is_applied' => $job['is_applied'] ?? 0
    ],
    'company_info' => [
        'recruiter_id' => intval($job['recruiter_id']),
        'company_name' => $job['company_name'],
        'company_logo' => $job['company_logo'],
        'industry' => $job['industry'],
        'website' => $job['website'],
        'location' => $job['company_location'],
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

mysqli_close($conn);

echo json_encode([
    "message" => "Job details fetched successfully",
    "status" => true,
    "data" => $formatted_job,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
