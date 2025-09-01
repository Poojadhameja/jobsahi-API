<?php
// create_job.php - Create new job posting (Admin, Recruiter access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter']); // returns array

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$title = isset($data['title']) ? $data['title'] : '';
$description = isset($data['description']) ? $data['description'] : '';
$location = isset($data['location']) ? $data['location'] : '';
$skills_required = isset($data['skills_required']) ? $data['skills_required'] : '';
$salary_min = isset($data['salary_min']) ? $data['salary_min'] : 0;
$salary_max = isset($data['salary_max']) ? $data['salary_max'] : 0;
$job_type = isset($data['job_type']) ? $data['job_type'] : '';
$experience_required = isset($data['experience_required']) ? $data['experience_required'] : '';
$application_deadline = isset($data['application_deadline']) ? $data['application_deadline'] : null;
$is_remote = isset($data['is_remote']) ? $data['is_remote'] : 0;
$no_of_vacancies = isset($data['no_of_vacancies']) ? $data['no_of_vacancies'] : 1;
$status = isset($data['status']) ? $data['status'] : 'open';


try {
    $stmt = $conn->prepare("INSERT INTO jobs (recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issssddssssss", $recruiter_id, $title, $description, $location, $skills_required, $salary_min, $salary_max, $job_type, $experience_required, $application_deadline, $is_remote, $no_of_vacancies, $status);
    
    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Job created successfully",
            "job_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to create job",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
