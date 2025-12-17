<?php
// interview_detail.php - Get Interview by ID with Panel (with admin_action logic)
require_once '../cors.php';

// ✅ Authenticate (only admin and student allowed)
$decoded = authenticateJWT(['admin', 'student']);  
$user_role = strtolower($decoded['role']);  // role from JWT payload

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

$interview_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($interview_id <= 0) {
    echo json_encode(["message" => "Valid interview ID is required", "status" => false]);
    exit;
}

/*
===================================================
 Role-based Filtering Logic
===================================================
- Admin  → can see all interviews (pending/approved/rejected)
- Student → only see own interviews with admin_action = 'approved'
===================================================
*/

// ✅ Get student_profile_id for student role to verify ownership
$student_profile_id = null;
$user_id = $decoded['user_id'] ?? $decoded['id'] ?? null;

if ($user_role === 'student' && $user_id) {
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
        exit;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student_profile_id = intval($result->fetch_assoc()['id']);
    }
    $stmt->close();
    
    if (!$student_profile_id) {
        echo json_encode(["message" => "Student profile not found", "status" => false]);
        exit;
    }
}

// ---- Build SQL with JOINs to get all comprehensive details ----
$sql = "SELECT 
            i.id AS interview_id,
            i.application_id,
            i.scheduled_at,
            i.mode,
            i.platform_name,
            i.interview_link,
            i.location AS interview_location,
            i.status AS interview_status,
            i.interview_info,
            i.created_at AS interview_created_at,
            i.modified_at AS interview_modified_at,
            i.deleted_at,
            i.admin_action,
            a.id AS application_id_full,
            a.student_id,
            a.status AS application_status,
            a.applied_at,
            a.cover_letter,
            j.id AS job_id,
            j.title AS job_title,
            j.description AS job_description,
            j.location AS job_location,
            j.job_type,
            j.salary_min,
            j.salary_max,
            j.experience_required,
            j.skills_required,
            j.application_deadline,
            j.is_remote,
            j.no_of_vacancies,
            rp.id AS recruiter_id,
            rp.company_name,
            rp.company_logo,
            rp.location AS company_location
        FROM interviews i
        INNER JOIN applications a ON i.application_id = a.id
        INNER JOIN jobs j ON a.job_id = j.id
        INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
        WHERE i.id = ?";

$params = [$interview_id];
$types = "i";

// ✅ Role-based filter on admin_action
if ($user_role !== 'admin') {
    $sql .= " AND i.admin_action = 'approved'";
}

// ✅ Verify student ownership for student role
if ($user_role === 'student' && $student_profile_id) {
    $sql .= " AND a.student_id = ?";
    $params[] = $student_profile_id;
    $types .= "i";
}

$sql .= " LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo json_encode(["message" => "Database prepare error: " . mysqli_error($conn), "status" => false]);
    exit;
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(["message" => "Interview not found or not accessible", "status" => false]);
    exit;
}

$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// ---- Fetch Interview Panel ----
$panel_sql = "SELECT id, interview_id, panelist_name, feedback AS panel_feedback, rating
              FROM interview_panel 
              WHERE interview_id = ?";
$panel_stmt = mysqli_prepare($conn, $panel_sql);
mysqli_stmt_bind_param($panel_stmt, "i", $interview_id);
mysqli_stmt_execute($panel_stmt);
$panel_result = mysqli_stmt_get_result($panel_stmt);

$panels = [];
while ($panel_row = mysqli_fetch_assoc($panel_result)) {
    $panels[] = [
        "id" => (int)$panel_row['id'],
        "panelist_name" => $panel_row['panelist_name'],
        "feedback" => $panel_row['panel_feedback'],
        "rating" => $panel_row['rating'] !== null ? (float)$panel_row['rating'] : null
    ];
}
mysqli_stmt_close($panel_stmt);

// ✅ Setup base URL for company logos
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$logo_base = '/jobsahi-API/api/uploads/recruiter_logo/';

// ✅ Handle company_logo URL conversion (R2 support + local files)
$company_logo = $row['company_logo'] ?? "";
if (!empty($company_logo)) {
    // Check if it's already an R2 URL
    if (strpos($company_logo, 'http') === 0 && 
        (strpos($company_logo, 'r2.dev') !== false || 
         strpos($company_logo, 'r2.cloudflarestorage.com') !== false)) {
        // Already R2 URL - use directly
        $row['company_logo'] = $company_logo;
    } else {
        // Local file path - convert to full URL
        $clean_logo = str_replace(["\\", "/uploads/recruiter_logo/", "./", "../"], "", $company_logo);
        $local_logo_path = __DIR__ . '/../uploads/recruiter_logo/' . $clean_logo;
        if (file_exists($local_logo_path)) {
            $row['company_logo'] = $protocol . $host . $logo_base . $clean_logo;
        } else {
            // File doesn't exist locally, keep original or set to empty
            $row['company_logo'] = $company_logo;
        }
    }
} else {
    $row['company_logo'] = "";
}

// ---- Build comprehensive response with all details ----
// Interview-specific details in separate object to avoid confusion with job location
$interview_data = [
    "interview" => [
        "id" => (int)$row['interview_id'],
        "application_id" => (int)$row['application_id'],
        "scheduled_at" => $row['scheduled_at'],
        "mode" => $row['mode'],
        "status" => $row['interview_status'],
        "location" => $row['mode'] === 'offline' ? $row['interview_location'] : null,
        "platform_name" => $row['mode'] === 'online' ? $row['platform_name'] : null,
        "interview_link" => $row['mode'] === 'online' ? $row['interview_link'] : null,
        "interview_info" => $row['interview_info'],
        "created_at" => $row['interview_created_at'],
        "modified_at" => $row['interview_modified_at'],
        "admin_action" => $row['admin_action'],
        "panel" => $panels
    ],
    "application" => [
        "id" => (int)$row['application_id_full'],
        "student_id" => (int)$row['student_id'],
        "status" => $row['application_status'],
        "applied_at" => $row['applied_at'],
        "cover_letter" => $row['cover_letter']
    ],
    "job" => [
        "id" => (int)$row['job_id'],
        "title" => $row['job_title'],
        "description" => $row['job_description'],
        "location" => $row['job_location'],
        "job_type" => $row['job_type'],
        "salary_min" => $row['salary_min'] !== null ? (float)$row['salary_min'] : null,
        "salary_max" => $row['salary_max'] !== null ? (float)$row['salary_max'] : null,
        "experience_required" => $row['experience_required'],
        "skills_required" => $row['skills_required'],
        "application_deadline" => $row['application_deadline'],
        "is_remote" => (bool)$row['is_remote'],
        "no_of_vacancies" => $row['no_of_vacancies'] !== null ? (int)$row['no_of_vacancies'] : null
    ],
        "company" => [
            "id" => (int)$row['recruiter_id'],
            "company_name" => $row['company_name'],
            "company_logo" => $row['company_logo'],
            "location" => $row['company_location']
        ]
];

echo json_encode([
    "message" => "Interview detail fetched successfully",
    "status" => true,
    "role" => $user_role,
    "data" => $interview_data,
    "timestamp" => date('Y-m-d H:i:s')
]);

mysqli_close($conn);
?>
