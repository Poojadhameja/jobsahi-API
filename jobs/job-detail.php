<?php
// job-detail.php - Single Job Detail API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Authorization, X-Requested-With');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// Get job ID from URL parameter
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id <= 0) {
    echo json_encode(["message" => "Valid job ID is required", "status" => false]);
    exit;
}

// Build detailed job query with recruiter information
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
            j.created_at,
            -- Recruiter information
            rp.company_name,
            rp.company_logo,
            rp.industry,
            rp.website,
            rp.location as company_location,
            -- Job statistics
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS total_views,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS total_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'applied') AS pending_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'shortlisted') AS shortlisted_applications,
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'selected') AS selected_applications,
            (SELECT COUNT(*) FROM saved_jobs s WHERE s.job_id = j.id) AS times_saved
        FROM jobs j
        LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE j.id = ?";

// Prepare statement
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Query error: " . mysqli_error($conn), "status" => false]);
    exit;
}

// Bind parameter
mysqli_stmt_bind_param($stmt, "i", $job_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    echo json_encode(["message" => "Job not found", "status" => false]);
    exit;
}

$job = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Format the response data
$formatted_job = [
    'job_info' => [
        'id' => $job['id'],
        'title' => $job['title'],
        'description' => $job['description'],
        'location' => $job['location'],
        'skills_required' => explode(',', $job['skills_required']), // Convert to array
        'salary_min' => floatval($job['salary_min']),
        'salary_max' => floatval($job['salary_max']),
        'job_type' => $job['job_type'],
        'experience_required' => $job['experience_required'],
        'application_deadline' => $job['application_deadline'],
        'is_remote' => (bool)$job['is_remote'],
        'no_of_vacancies' => intval($job['no_of_vacancies']),
        'status' => $job['status'],
        'created_at' => $job['created_at']
    ],
    'company_info' => [
        'recruiter_id' => $job['recruiter_id'],
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

// Optional: Get similar jobs based on location or job_type
if (isset($_GET['include_similar']) && $_GET['include_similar'] == 'true') {
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
                    AND (j.location = ? OR j.job_type = ?)
                    ORDER BY j.created_at DESC
                    LIMIT 5";
    
    $similar_stmt = mysqli_prepare($conn, $similar_sql);
    if ($similar_stmt) {
        mysqli_stmt_bind_param($similar_stmt, "iss", $job_id, $job['location'], $job['job_type']);
        mysqli_stmt_execute($similar_stmt);
        $similar_result = mysqli_stmt_get_result($similar_stmt);
        
        $similar_jobs = [];
        while ($similar_row = mysqli_fetch_assoc($similar_result)) {
            $similar_jobs[] = [
                'id' => $similar_row['id'],
                'title' => $similar_row['title'],
                'location' => $similar_row['location'],
                'salary_min' => floatval($similar_row['salary_min']),
                'salary_max' => floatval($similar_row['salary_max']),
                'job_type' => $similar_row['job_type'],
                'company_name' => $similar_row['company_name']
            ];
        }
        
        $formatted_job['similar_jobs'] = $similar_jobs;
        mysqli_stmt_close($similar_stmt);
    }
}

mysqli_close($conn);

// Return JSON response
echo json_encode([
    "message" => "Job details fetched successfully",
    "status" => true,
    "data" => $formatted_job,
    "timestamp" => date('Y-m-d H:i:s')
]);
?>