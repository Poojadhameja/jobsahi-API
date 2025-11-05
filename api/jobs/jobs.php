<?php
// jobs.php - Job Listings API (Role-based access with admin_action filter)
require_once '../cors.php';

// ✅ Authenticate all roles
$decoded = authenticateJWT(['student', 'admin', 'recruiter', 'institute']);

// Ensure we got the role correctly
$userRole = isset($decoded['role']) ? $decoded['role'] : null;

if (!$userRole) {
    echo json_encode(["message" => "Unauthorized: Role not found in token", "status" => false]);
    exit;
}

// Get student_id if user is student
$student_profile_id = null;
if ($userRole === 'student') {
    $user_id = null;
    if (isset($decoded['id'])) {
        $user_id = $decoded['id'];
    } elseif (isset($decoded['user_id'])) {
        $user_id = $decoded['user_id'];
    } elseif (isset($decoded['student_id'])) {
        $user_id = $decoded['student_id'];
    }
    
    if ($user_id) {
        // Get student profile ID from user_id
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

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// ✅ Check if job ID is passed (job_by_id)
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id > 0) {
    // Single Job Details Mode
    $sql = "SELECT 
                j.id,
                j.recruiter_id,
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
                j.is_featured,
                j.created_at,
                rp.company_name,
                ci.person_name,
                ci.phone,
                ci.additional_contact,
                (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS views
            FROM jobs j
            LEFT JOIN recruiter_profiles rp 
                ON j.recruiter_id = rp.id
            LEFT JOIN recruiter_company_info ci 
                ON ci.job_id = j.id
            WHERE j.id = ?";


    // ✅ Role-based visibility filter
    if (!in_array($userRole, ['admin', 'recruiter'])) {
        $sql .= " AND j.admin_action = 'approved'";
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $job_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(["status" => false, "message" => "Job not found or not accessible"]);
        exit;
    }

    $job = mysqli_fetch_assoc($result);

    // ✅ Add "is_saved" flag for students
    if ($userRole === 'student' && $student_profile_id) {
        $check_saved_sql = "SELECT 1 FROM saved_jobs WHERE student_id = ? AND job_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_saved_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $student_profile_id, $job_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $job['is_saved'] = (mysqli_num_rows($check_result) > 0) ? 1 : 0;
        mysqli_stmt_close($check_stmt);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    echo json_encode([
        "status" => true,
        "message" => "Job details fetched successfully",
        "data" => $job,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    exit;
}

// -------------------------------------------------------------
// ✅ ELSE: Default Listing (Existing Logic)
// -------------------------------------------------------------

// Collect filters from query params
$filters = [];
$params = [];
$types  = "";

// ✅ Role-based filter for admin_action
if (in_array($userRole, ['admin', 'recruiter']))  {
    // Admin sees both pending + approved
    $filters[] = "(j.admin_action = 'pending' OR j.admin_action = 'approved')";
} else {
    // Other roles only see approved jobs
    $filters[] = "j.admin_action = 'approved'";
}

// Keyword search (title/description)
if (!empty($_GET['keyword'])) {
    $filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $kw = "%" . $_GET['keyword'] . "%";
    $params[] = $kw;
    $params[] = $kw;
    $types   .= "ss";
}

// Location filter
if (!empty($_GET['location'])) {
    $filters[] = "j.location = ?";
    $params[] = $_GET['location'];
    $types   .= "s";
}

// Job type filter
if (!empty($_GET['job_type'])) {
    $filters[] = "j.job_type = ?";
    $params[] = $_GET['job_type'];
    $types   .= "s";
}

// Status filter
if (!empty($_GET['status'])) {
    $filters[] = "j.status = ?";
    $params[] = $_GET['status'];
    $types   .= "s";
}

// Remote filter
if (!empty($_GET['is_remote'])) {
    $filters[] = "j.is_remote = ?";
    $params[] = $_GET['is_remote'];
    $types   .= "i";
}

// Featured jobs filter
if (isset($_GET['featured']) && $_GET['featured'] == 'true') {
    $filters[] = "j.is_featured = 1";
}

// Build query
$sql = "SELECT 
            j.id,
            j.recruiter_id,
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
            j.is_featured,
            j.created_at,
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS views,
            rp.company_name";

// Check if job is saved for students - using saved_jobs table
if ($userRole === 'student' && $student_profile_id) {
    $sql .= ",
            CASE 
                WHEN EXISTS (SELECT 1 FROM saved_jobs sj WHERE sj.student_id = ? AND sj.job_id = j.id) THEN 1 
                ELSE 0 
            END as is_saved";
}

$sql .= " FROM jobs j
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id";

if (!empty($filters)) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}

$sql .= " ORDER BY j.created_at DESC";

// Prepare statement
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// Bind parameters - add student_id if needed
if ($userRole === 'student' && $student_profile_id) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, "i" . $types, $student_profile_id, ...$params);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $student_profile_id);
    }
} else {
    // Bind filters for non-students
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$jobs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $jobs[] = $row;
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

// Return JSON
echo json_encode([
    "message" => "Jobs fetched successfully",
    "status" => true,
    "count" => count($jobs),
    "data" => $jobs,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
