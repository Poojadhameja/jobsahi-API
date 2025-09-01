<?php
// update_job.php - Update job posting (Admin access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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
$decoded = authenticateJWT(['admin']); // returns array

// Get job ID from URL parameter
$job_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($job_id)) {
    echo json_encode([
        "status" => false,
        "message" => "Job ID is required"
    ]);
    exit();
}

// Get PUT data
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
    $stmt = $conn->prepare("UPDATE jobs SET title = ?, description = ?, location = ?, skills_required = ?, salary_min = ?, salary_max = ?, job_type = ?, experience_required = ?, application_deadline = ?, is_remote = ?, no_of_vacancies = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssddssssssi", $title, $description, $location, $skills_required, $salary_min, $salary_max, $job_type, $experience_required, $application_deadline, $is_remote, $no_of_vacancies, $status, $job_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "status" => true,
                "message" => "Job updated successfully",
                "job_id" => $job_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Job not found or no changes made"
            ]);
        }
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to update job",
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