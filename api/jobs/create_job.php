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

// ✅ Get recruiter_id from URL parameter
$recruiter_id = isset($_GET['recruiter_id']) ? intval($_GET['recruiter_id']) : 0;

// Validate recruiter_id
if ($recruiter_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Valid recruiter_id is required in URL parameters"
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

// Validate JSON data
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid JSON data"
    ]);
    exit();
}

$title = isset($data['title']) ? trim($data['title']) : '';
$description = isset($data['description']) ? trim($data['description']) : '';
$location = isset($data['location']) ? trim($data['location']) : '';
$skills_required = isset($data['skills_required']) ? trim($data['skills_required']) : '';
$salary_min = isset($data['salary_min']) ? floatval($data['salary_min']) : 0;
$salary_max = isset($data['salary_max']) ? floatval($data['salary_max']) : 0;
$job_type = isset($data['job_type']) ? trim($data['job_type']) : '';
$experience_required = isset($data['experience_required']) ? trim($data['experience_required']) : '';
$application_deadline = isset($data['application_deadline']) ? $data['application_deadline'] : null;
$is_remote = isset($data['is_remote']) ? intval($data['is_remote']) : 0;
$no_of_vacancies = isset($data['no_of_vacancies']) ? intval($data['no_of_vacancies']) : 1;
$status = isset($data['status']) ? trim($data['status']) : 'open';

// Basic validation for required fields
if (empty($title)) {
    echo json_encode([
        "status" => false,
        "message" => "Job title is required"
    ]);
    exit();
}

if (empty($description)) {
    echo json_encode([
        "status" => false,
        "message" => "Job description is required"
    ]);
    exit();
}

// Validate salary range if provided
if ($salary_min > 0 && $salary_max > 0 && $salary_min > $salary_max) {
    echo json_encode([
        "status" => false,
        "message" => "Minimum salary cannot be greater than maximum salary"
    ]);
    exit();
}

try {
    // ✅ Check if recruiter profile exists (matches foreign key constraint)
    $check_stmt = $conn->prepare("SELECT id FROM recruiter_profiles WHERE id = ?");
    $check_stmt->bind_param("i", $recruiter_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid recruiter profile ID"
        ]);
        exit();
    }
    
    // Insert job
    $stmt = $conn->prepare("INSERT INTO jobs (recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("issssddsssiii", 
        $recruiter_id, 
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
        $status
    );
    
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