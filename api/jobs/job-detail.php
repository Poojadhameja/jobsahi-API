<?php
require_once '../cors.php';
require_once '../auth/auth_middleware.php';

// ------------------------------------------
// 🔐 OPTIONAL AUTHENTICATION (allows public access)
// ------------------------------------------
$decodedToken = authenticateJWTOptional(['student', 'recruiter', 'admin']);
$user_role = $decodedToken ? strtolower($decodedToken['role']) : null;
$user_id = $decodedToken ? intval($decodedToken['user_id'] ?? $decodedToken['id'] ?? 0) : 0;

// DB connection
require_once '../db.php';

// ------------------------------------------
// 🎯 STUDENT PROFILE ID (for saved/applied flags)
// ------------------------------------------
$student_profile_id = null;
if ($user_role === 'student') {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r) $student_profile_id = $r['id'];
    $stmt->close();
}

// ------------------------------------------
// 🎯 RECRUITER PROFILE ID (to restrict to HIS jobs only)
// ------------------------------------------
$recruiter_profile_id = null;
if ($user_role === 'recruiter') {
    $stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rp = $stmt->get_result()->fetch_assoc();
    if ($rp) $recruiter_profile_id = intval($rp['id']);
    $stmt->close();
}

// ------------------------------------------
// ✅ Validate job ID
// ------------------------------------------
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($job_id <= 0) {
    echo json_encode(["status" => false, "message" => "Valid job ID required"]);
    exit;
}

// ------------------------------------------
// 🎯 VISIBILITY RULES
// ------------------------------------------

// Admin → can see ALL jobs (no admin_action logic needed)
$visibilityCondition = "1=1";

// Public/Student → can see ONLY approved jobs
if ($user_role === 'student' || !$user_role) {
    $visibilityCondition = "j.admin_action = 'approved'";
}

// Recruiter → can see ONLY his own jobs (NO admin_action needed)
if ($user_role === 'recruiter' && $recruiter_profile_id) {
    $visibilityCondition = "j.recruiter_id = $recruiter_profile_id";
}

// ------------------------------------------
// 🔍 MAIN QUERY
// ------------------------------------------
$sql = "SELECT 
            j.*,
            jc.category_name,
            rp.company_name, rp.company_logo, rp.industry, rp.website, rp.location AS company_location,
            rci.person_name, rci.phone, rci.additional_contact,
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS total_views,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS total_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status='applied') AS pending_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status='shortlisted') AS shortlisted_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status='selected') AS selected_applications,
            (SELECT COUNT(*) FROM saved_jobs sj WHERE sj.job_id = j.id) AS times_saved";

if ($user_role === 'student' && $student_profile_id) {
    $sql .= ",
        CASE WHEN EXISTS(SELECT 1 FROM saved_jobs sj WHERE sj.job_id=j.id AND sj.student_id=$student_profile_id) THEN 1 ELSE 0 END AS is_saved,
        CASE WHEN EXISTS(SELECT 1 FROM applications a WHERE a.job_id=j.id AND a.student_id=$student_profile_id AND (a.deleted_at IS NULL OR a.deleted_at='0000-00-00 00:00:00')) 
            THEN 1 ELSE 0 END AS is_applied";
}

$sql .= " 
        FROM jobs j
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        LEFT JOIN recruiter_company_info rci ON rci.job_id = j.id
        LEFT JOIN job_category jc ON j.category_id = jc.id
        WHERE j.id = ? AND $visibilityCondition
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status" => false, "message" => "Job not found or not allowed"]);
    exit;
}

$job = $res->fetch_assoc();

// ------------------------------------------
// 🖼 Fix company logo URL (R2 support + local files)
// ------------------------------------------
if (!empty($job['company_logo'])) {
    // Check if it's already an R2 URL
    if (strpos($job['company_logo'], 'http') === 0 && 
        (strpos($job['company_logo'], 'r2.dev') !== false || 
         strpos($job['company_logo'], 'r2.cloudflarestorage.com') !== false)) {
        // Already R2 URL - use directly
        // No change needed
    } else {
        // Local file path - convert to full URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $logoBase = "/jobsahi-API/api/uploads/recruiter_logo/";
        $clean_logo = str_replace(["\\", "/uploads/recruiter_logo/", "./", "../"], "", $job['company_logo']);
        $local_logo_path = __DIR__ . "/../uploads/recruiter_logo/" . $clean_logo;
        if (file_exists($local_logo_path)) {
            $job['company_logo'] = $protocol . $host . $logoBase . $clean_logo;
        }
    }
}

// ------------------------------------------
// 📦 FORMAT RESPONSE
// ------------------------------------------
$response = [
    "job_info" => [
        "id" => intval($job['id']),
        "title" => $job['title'],
        "description" => $job['description'],
        "category_id" => intval($job['category_id']),
        "category_name" => $job['category_name'],
        "location" => $job['location'],
        "skills_required" => array_map("trim", explode(",", $job['skills_required'])),
        "salary_min" => floatval($job['salary_min']),
        "salary_max" => floatval($job['salary_max']),
        "job_type" => $job['job_type'],
        "experience_required" => $job['experience_required'],
        "application_deadline" => $job['application_deadline'],
        "is_remote" => (bool)$job['is_remote'],
        "no_of_vacancies" => intval($job['no_of_vacancies']),
        "status" => $job['status'],
        "created_at" => $job['created_at'],
        "person_name" => $job['person_name'],
        "phone" => $job['phone'],
        "additional_contact" => $job['additional_contact'],
        "is_saved" => intval($job['is_saved'] ?? 0),
        "is_applied" => intval($job['is_applied'] ?? 0)
    ],
    "company_info" => [
        "recruiter_id" => intval($job['recruiter_id']),
        "company_name" => $job['company_name'],
        "company_logo" => $job['company_logo'],
        "industry" => $job['industry'],
        "website" => $job['website'],
        "location" => $job['company_location']
    ],
    "statistics" => [
        "total_views" => intval($job['total_views']),
        "total_applications" => intval($job['total_applications']),
        "pending_applications" => intval($job['pending_applications']),
        "shortlisted_applications" => intval($job['shortlisted_applications']),
        "selected_applications" => intval($job['selected_applications']),
        "times_saved" => intval($job['times_saved'])
    ]
];

echo json_encode([
    "status" => true,
    "message" => "Job details fetched successfully",
    "data" => $response
]);
?>
