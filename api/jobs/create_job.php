<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["message" => "Only POST requests allowed", "status" => false]);
    exit;
}

// ✅ Authenticate recruiter
$current_user = authenticateJWT(['recruiter', 'admin']);
$user_role = strtolower($current_user['role']);
$user_id = $current_user['user_id'];

if ($user_role !== 'recruiter') {
    echo json_encode(["message" => "Only recruiters can create jobs", "status" => false]);
    exit;
}

// ✅ Get recruiter_id
$stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? AND admin_action = 'approved' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($recruiter_id);
$stmt->fetch();
$stmt->close();

if (!$recruiter_id) {
    echo json_encode(["message" => "Recruiter profile not found or not approved", "status" => false]);
    exit;
}

// ✅ Get input data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(["message" => "Invalid JSON input", "status" => false]);
    exit;
}

// Job details
$title = $input['title'] ?? '';
$description = $input['description'] ?? '';
$category_name = $input['category_name'] ?? '';
$location = $input['location'] ?? '';
$skills_required = $input['skills_required'] ?? '';
$salary_min = $input['salary_min'] ?? 0;
$salary_max = $input['salary_max'] ?? 0;
$job_type = $input['job_type'] ?? 'full_time';
$experience_required = $input['experience_required'] ?? '';
$application_deadline = $input['application_deadline'] ?? null;
$is_remote = $input['is_remote'] ?? 0;
$no_of_vacancies = $input['no_of_vacancies'] ?? 1;
$vacancyStatus = $input['vacancyStatus'] ?? 'open';
$status = $input['status'] ?? 'open';
$admin_action = 'pending';

// Recruiter info
$person_name = $input['person_name'] ?? '';
$phone = $input['phone'] ?? '';
$additional_contact = $input['additional_contact'] ?? '';

// ✅ Start Transaction
$conn->begin_transaction();

try {
    // 1️⃣ Category check or insert
    $category_id = null;
    $cat_check = $conn->prepare("SELECT id FROM job_category WHERE LOWER(category_name) = LOWER(?) LIMIT 1");
    $cat_check->bind_param("s", $category_name);
    $cat_check->execute();
    $cat_check->bind_result($category_id);
    $cat_check->fetch();
    $cat_check->close();

    if (!$category_id) {
        $cat_insert = $conn->prepare("INSERT INTO job_category (category_name, created_at) VALUES (?, NOW())");
        $cat_insert->bind_param("s", $category_name);
        $cat_insert->execute();
        $category_id = $cat_insert->insert_id;
        $cat_insert->close();
    }

    // 2️⃣ Insert into jobs (without company_info_id first)
    $job_sql = "INSERT INTO jobs (
    recruiter_id, category_id, title, description, location, skills_required,
    salary_min, salary_max, job_type, experience_required, application_deadline,
    is_remote, no_of_vacancies, status, admin_action
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$job_stmt = $conn->prepare($job_sql);
$job_stmt->bind_param(
    "iissssddsssiiss",
    $recruiter_id,
    $category_id,
    $title,
    $description,
    $location,
    $skills_required,
    $salary_min,
    $salary_max,
    $job_type,
    $experience_required,
    $application_deadline,
    $is_remote,
    $no_of_vacancies,
    $status,
    $admin_action
);

    $job_stmt->execute();
    $job_id = $job_stmt->insert_id;
    $job_stmt->close();

    // 3️⃣ Insert company info
    $company_sql = "INSERT INTO recruiter_company_info (job_id, recruiter_id, person_name, phone, additional_contact, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())";
    $company_stmt = $conn->prepare($company_sql);
    $company_stmt->bind_param("iisss", $job_id, $recruiter_id, $person_name, $phone, $additional_contact);
    $company_stmt->execute();
    $company_info_id = $company_stmt->insert_id;
    $company_stmt->close();

    // 4️⃣ Update the job record with company_info_id
    $update_sql = "UPDATE jobs SET company_info_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $company_info_id, $job_id);
    $update_stmt->execute();
    $update_stmt->close();

    // ✅ Commit transaction
    $conn->commit();

    echo json_encode([
        "message" => "Job, category, and recruiter contact info created successfully",
        "status" => true,
        "job_id" => $job_id,
        "category_id" => $category_id,
        "company_info_id" => $company_info_id
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["message" => $e->getMessage(), "status" => false]);
}

$conn->close();
?>
