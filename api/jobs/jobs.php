<?php
// jobs.php - Job Listings API (Enhanced with Category & Company Info)
require_once '../cors.php';

// ✅ Authenticate all roles
$current_user = authenticateJWT(['student', 'admin', 'recruiter', 'institute']);
$user_role = strtolower($current_user['role']);
$user_id = $current_user['user_id']; // ✅ user_id from token

if (!$user_role) {
    echo json_encode(["message" => "Unauthorized: Role not found in token", "status" => false]);
    exit;
}

// ✅ Allow only GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["message" => "Only GET requests allowed", "status" => false]);
    exit;
}

include "../db.php";

if (!$conn) {
    echo json_encode(["message" => "DB connection failed: " . mysqli_connect_error(), "status" => false]);
    exit;
}

// --- Initialize filters ---
$filters = [];
$params = [];
$types  = "";

// ✅ Role-based filter for admin_action
if ($user_role === 'admin') {
    // Admin sees all pending and approved
    $filters[] = "(j.admin_action = 'pending' OR j.admin_action = 'approved')";
} elseif ($user_role === 'recruiter') {
    // ✅ Recruiter sees only their jobs (pending + approved)
    $stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? AND admin_action = 'approved' AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($recruiter_id);
    $stmt->fetch();
    $stmt->close();

    if (!$recruiter_id) {
        echo json_encode([
            "message" => "Recruiter profile not approved or not found",
            "status" => false,
            "count" => 0,
            "data" => []
        ]);
        exit;
    }

    $filters[] = "j.recruiter_id = ?";
    $filters[] = "(j.admin_action = 'approved' OR j.admin_action = 'pending')";
    $params[] = $recruiter_id;
    $types .= "i";
} else {
    // Student or Institute → only approved jobs
    $filters[] = "j.admin_action = 'approved'";
}

// --- Additional filters (optional) ---
if (!empty($_GET['keyword'])) {
    $filters[] = "(j.title LIKE ? OR j.description LIKE ?)";
    $kw = "%" . $_GET['keyword'] . "%";
    $params[] = $kw;
    $params[] = $kw;
    $types   .= "ss";
}

if (!empty($_GET['location'])) {
    $filters[] = "j.location = ?";
    $params[] = $_GET['location'];
    $types   .= "s";
}

if (!empty($_GET['job_type'])) {
    $filters[] = "j.job_type = ?";
    $params[] = $_GET['job_type'];
    $types   .= "s";
}

if (!empty($_GET['status'])) {
    $filters[] = "j.status = ?";
    $params[] = $_GET['status'];
    $types   .= "s";
}

if (!empty($_GET['is_remote'])) {
    $filters[] = "j.is_remote = ?";
    $params[] = $_GET['is_remote'];
    $types   .= "i";
}

// --- Build main SQL ---
$sql = "SELECT 
            j.id,
            j.recruiter_id,
            j.category_id,
            c.category_name,
            j.company_info_id,
            ci.person_name,
            ci.phone,
            ci.additional_contact,
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
            (SELECT COUNT(*) FROM job_views v WHERE v.job_id = j.id) AS views
        FROM jobs j
        LEFT JOIN job_category c ON j.category_id = c.id
        LEFT JOIN recruiter_company_info ci ON j.company_info_id = ci.id";

// --- Apply filters ---
if (!empty($filters)) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}

$sql .= " ORDER BY j.created_at DESC";

// --- Prepare & execute ---
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["message" => "Query preparation error: " . $conn->error, "status" => false]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $jobs[] = [
        "id" => $row['id'],
        "title" => $row['title'],
        "description" => $row['description'],
        "category_id" => $row['category_id'],
        "category_name" => $row['category_name'],
        "recruiter_id" => $row['recruiter_id'],
        "company_info_id" => $row['company_info_id'],
        "person_name" => $row['person_name'],
        "phone" => $row['phone'],
        "additional_contact" => $row['additional_contact'],
        "location" => $row['location'],
        "skills_required" => $row['skills_required'],
        "salary_min" => $row['salary_min'],
        "salary_max" => $row['salary_max'],
        "job_type" => $row['job_type'],
        "experience_required" => $row['experience_required'],
        "application_deadline" => $row['application_deadline'],
        "is_remote" => $row['is_remote'],
        "no_of_vacancies" => $row['no_of_vacancies'],
        "status" => $row['status'],
        "admin_action" => $row['admin_action'],
        "views" => intval($row['views']),
        "created_at" => $row['created_at']
    ];
}

$stmt->close();
$conn->close();

// --- Response ---
echo json_encode([
    "message" => "Jobs fetched successfully",
    "status" => true,
    "count" => count($jobs),
    "data" => $jobs,
    "user_role" => $user_role,
    "timestamp" => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>
