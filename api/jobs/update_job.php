<?php
// update_job.php - Update Job + Category + Recruiter Company Info
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT (Admin or Recruiter)
$current_user = authenticateJWT(['admin', 'recruiter']);
$user_role = strtolower($current_user['role']);
$user_id = $current_user['user_id'];

// ✅ Validate job_id
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (empty($job_id)) {
    echo json_encode(["status" => false, "message" => "Job ID is required"]);
    exit;
}

// ✅ Get input
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["status" => false, "message" => "Invalid JSON input"]);
    exit;
}

// Extract fields
$title = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$category_name = trim($data['category_name'] ?? '');
$location = trim($data['location'] ?? '');
$skills_required = trim($data['skills_required'] ?? '');
$salary_min = floatval($data['salary_min'] ?? 0);
$salary_max = floatval($data['salary_max'] ?? 0);
$job_type = $data['job_type'] ?? '';
$experience_required = trim($data['experience_required'] ?? '');
$application_deadline = $data['application_deadline'] ?? null;
$is_remote = intval($data['is_remote'] ?? 0);
$no_of_vacancies = intval($data['no_of_vacancies'] ?? 1);
$status = $data['status'] ?? 'open';

// ✅ Recruiter contact info
$person_name = trim($data['person_name'] ?? '');
$phone = trim($data['phone'] ?? '');
$additional_contact = trim($data['additional_contact'] ?? '');

// ✅ Begin transaction
$conn->begin_transaction();

try {
    // --- 1️⃣ Verify recruiter access ---
    if ($user_role === 'recruiter') {
        $check = $conn->prepare("SELECT j.id, j.recruiter_id FROM jobs j 
                                 INNER JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
                                 WHERE j.id = ? AND rp.user_id = ? LIMIT 1");
        $check->bind_param("ii", $job_id, $user_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            throw new Exception("Unauthorized or job not found for this recruiter");
        }
        $check->close();
    }

    // --- 2️⃣ Handle Category Update or Create ---
    $category_id = null;
    if (!empty($category_name)) {
        $cat_check = $conn->prepare("SELECT id FROM categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1");
        $cat_check->bind_param("s", $category_name);
        $cat_check->execute();
        $cat_check->bind_result($category_id);
        $cat_check->fetch();
        $cat_check->close();

        if (!$category_id) {
            $cat_insert = $conn->prepare("INSERT INTO categories (category_name, created_at) VALUES (?, NOW())");
            $cat_insert->bind_param("s", $category_name);
            $cat_insert->execute();
            $category_id = $cat_insert->insert_id;
            $cat_insert->close();
        }
    }

    // --- 3️⃣ Update Job Main Table ---
    $update_job = $conn->prepare("
        UPDATE jobs 
        SET title = ?, 
            description = ?, 
            category_id = ?, 
            location = ?, 
            skills_required = ?, 
            salary_min = ?, 
            salary_max = ?, 
            job_type = ?, 
            experience_required = ?, 
            application_deadline = ?, 
            is_remote = ?, 
            no_of_vacancies = ?, 
            status = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_job->bind_param(
        "ssissddsssissi",
        $title,
        $description,
        $category_id,
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
        $job_id
    );

    if (!$update_job->execute()) {
        throw new Exception("Failed to update job: " . $update_job->error);
    }
    $update_job->close();

    // --- 4️⃣ Update Recruiter Company Info (if linked) ---
    $get_info = $conn->prepare("SELECT company_info_id FROM jobs WHERE id = ?");
    $get_info->bind_param("i", $job_id);
    $get_info->execute();
    $get_info->bind_result($company_info_id);
    $get_info->fetch();
    $get_info->close();

    if ($company_info_id) {
        // Update existing contact record
        $update_info = $conn->prepare("UPDATE recruiter_company_info 
            SET person_name = ?, phone = ?, additional_contact = ?, updated_at = NOW() 
            WHERE id = ?");
        $update_info->bind_param("sssi", $person_name, $phone, $additional_contact, $company_info_id);
        if (!$update_info->execute()) {
            throw new Exception("Failed to update recruiter contact info: " . $update_info->error);
        }
        $update_info->close();
    } else {
        // If not found, create a new contact and link it
        $insert_info = $conn->prepare("INSERT INTO recruiter_company_info (job_id, recruiter_id, person_name, phone, additional_contact, created_at)
                                       VALUES ((SELECT recruiter_id FROM jobs WHERE id = ?), 
                                               (SELECT recruiter_id FROM jobs WHERE id = ?), ?, ?, ?, NOW())");
        $insert_info->bind_param("iisss", $job_id, $job_id, $person_name, $phone, $additional_contact);
        $insert_info->execute();
        $new_info_id = $insert_info->insert_id;
        $insert_info->close();

        $link = $conn->prepare("UPDATE jobs SET company_info_id = ? WHERE id = ?");
        $link->bind_param("ii", $new_info_id, $job_id);
        $link->execute();
        $link->close();
    }

    // ✅ Commit transaction
    $conn->commit();

    echo json_encode([
        "status" => true,
        "message" => "Job, category, and recruiter contact info updated successfully",
        "job_id" => $job_id,
        "category_id" => $category_id,
        "company_info_id" => $company_info_id ?? $new_info_id ?? null
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>
