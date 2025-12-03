
<?php 
require_once '../cors.php';
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST allowed"]);
    exit;
}

$decoded = authenticateJWT(['recruiter', 'admin']);
$user_role = strtolower($decoded['role']);
$user_id   = intval($decoded['user_id']);

if ($user_role !== 'recruiter') {
    echo json_encode(["status" => false, "message" => "Only recruiters can create jobs"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($recruiter_id);
$stmt->fetch();
$stmt->close();

if (!$recruiter_id) {
    echo json_encode(["status" => false, "message" => "Recruiter profile not found"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

$title                 = $input['title'] ?? '';
$description           = $input['description'] ?? '';
$category_name         = $input['category_name'] ?? '';
$location              = $input['location'] ?? '';
$skills_required       = $input['skills_required'] ?? '';
$salary_min            = $input['salary_min'] ?? 0;
$salary_max            = $input['salary_max'] ?? 0;
$job_type              = $input['job_type'] ?? 'full_time';
$experience_required   = $input['experience_required'] ?? '';
$application_deadline  = $input['application_deadline'] ?? null;
$is_remote             = intval($input['is_remote'] ?? 0);
$no_of_vacancies       = intval($input['no_of_vacancies'] ?? 1);
$status                = $input['status'] ?? 'open';

$person_name           = $input['person_name'] ?? '';
$phone                 = $input['phone'] ?? '';
$additional_contact    = $input['additional_contact'] ?? '';

$conn->begin_transaction();

try {

    $category_id = null;

    $cat = $conn->prepare("SELECT id FROM job_category WHERE LOWER(category_name)=LOWER(?) LIMIT 1");
    $cat->bind_param("s", $category_name);
    $cat->execute();
    $cat->bind_result($category_id);
    $cat->fetch();
    $cat->close();

    if (!$category_id) {
        $ins = $conn->prepare("INSERT INTO job_category (category_name, created_at) VALUES (?, NOW())");
        $ins->bind_param("s", $category_name);
        $ins->execute();
        $category_id = $ins->insert_id;
        $ins->close();
    }

    // ✅ Default admin_action = 'approved' (by default jobs are approved)
    $admin_action = 'approved';
    
    // FIXED TYPE STRING:
    // i i s s s s d d s s s i i s s   = 15 params (added admin_action)
    $job_sql = "
        INSERT INTO jobs 
        (recruiter_id, category_id, title, description, location, skills_required,
         salary_min, salary_max, job_type, experience_required, application_deadline,
         is_remote, no_of_vacancies, status, admin_action)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $job_stmt = $conn->prepare($job_sql);
    // correct type string: i i s s s s d d s s s i i s s (added 's' for admin_action)
    $types = "iissssddsssiiss";
    $job_stmt->bind_param(
        $types,
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

    $csql = "
        INSERT INTO recruiter_company_info 
        (job_id, recruiter_id, person_name, phone, additional_contact, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";

    $cstmt = $conn->prepare($csql);
    $cstmt->bind_param("iisss", $job_id, $recruiter_id, $person_name, $phone, $additional_contact);
    $cstmt->execute();
    $company_info_id = $cstmt->insert_id;
    $cstmt->close();

    $up = $conn->prepare("UPDATE jobs SET company_info_id = ? WHERE id = ?");
    $up->bind_param("ii", $company_info_id, $job_id);
    $up->execute();
    $up->close();

    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Job created successfully",
        "job_id" => $job_id,
        "category_id" => $category_id,
        "company_info_id" => $company_info_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>
