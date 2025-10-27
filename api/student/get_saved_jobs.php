<?php
// get_saved_jobs.php - Get student's saved jobs list
require_once '../cors.php';

// ✅ Authenticate JWT (only student can access)
$decoded = authenticateJWT('student'); 

// Handle different key names from JWT payload safely
$user_id = null;
if (isset($decoded['id'])) {
    $user_id = $decoded['id'];
} elseif (isset($decoded['user_id'])) {
    $user_id = $decoded['user_id'];
} elseif (isset($decoded['student_id'])) {
    $user_id = $decoded['student_id'];
}

if (!$user_id) {
    echo json_encode([
        "message" => "Invalid token payload: student id missing",
        "status"  => false
    ]);
    exit;
}

// Get student profile ID from user_id
$check_student_sql = "SELECT id FROM student_profiles WHERE user_id = ?";
$check_student_stmt = mysqli_prepare($conn, $check_student_sql);
mysqli_stmt_bind_param($check_student_stmt, "i", $user_id);
mysqli_stmt_execute($check_student_stmt);
$student_result = mysqli_stmt_get_result($check_student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    echo json_encode([
        "message" => "Student profile not found. Please complete your profile.", 
        "status" => false,
        "user_id" => $user_id
    ]);
    mysqli_stmt_close($check_student_stmt);
    mysqli_close($conn);
    exit;
}

// Get the actual student profile ID
$student_profile = mysqli_fetch_assoc($student_result);
$student_id = $student_profile['id'];
mysqli_stmt_close($check_student_stmt);

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

// Get pagination parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Validate pagination
$limit = max(1, min(100, $limit)); // Limit between 1-100
$offset = max(0, $offset);

// Get saved jobs with job details
$sql = "SELECT 
            j.id,
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
            j.save_status,
            j.saved_by_student_id,
            rp.company_name,
            rp.company_logo,
            rp.industry,
            rp.website
        FROM jobs j
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE j.save_status = 1 
        AND j.saved_by_student_id = ?
        AND j.admin_action = 'approved'
        AND j.status = 'open'
        ORDER BY j.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Database error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($stmt, "iii", $student_id, $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$saved_jobs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $saved_jobs[] = [
        'job_id' => intval($row['id']),
        'title' => $row['title'],
        'description' => $row['description'],
        'location' => $row['location'],
        'skills_required' => !empty($row['skills_required']) ? array_map('trim', explode(',', $row['skills_required'])) : [],
        'salary_min' => floatval($row['salary_min']),
        'salary_max' => floatval($row['salary_max']),
        'job_type' => $row['job_type'],
        'experience_required' => $row['experience_required'],
        'application_deadline' => $row['application_deadline'],
        'is_remote' => (bool)$row['is_remote'],
        'no_of_vacancies' => intval($row['no_of_vacancies']),
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'save_status' => (bool)$row['save_status'],
        'company' => [
            'company_name' => $row['company_name'],
            'company_logo' => $row['company_logo'],
            'industry' => $row['industry'],
            'website' => $row['website']
        ]
    ];
}

mysqli_stmt_close($stmt);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM jobs WHERE save_status = 1 AND saved_by_student_id = ? AND admin_action = 'approved' AND status = 'open'";
$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "i", $student_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_count = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($count_stmt);

mysqli_close($conn);

echo json_encode([
    "message" => "Saved jobs retrieved successfully",
    "status" => true,
    "data" => $saved_jobs,
    "pagination" => [
        "total" => intval($total_count),
        "limit" => $limit,
        "offset" => $offset,
        "has_more" => ($offset + $limit) < $total_count
    ],
    "timestamp" => date('Y-m-d H:i:s')
]);
?>
