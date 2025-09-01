<?php
// get_recruiter_jobs.php - List jobs posted by recruiter (Admin, Recruiter access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow both roles
$decoded = authenticateJWT(['admin', 'recruiter']); // returns array

// Extract recruiter_id from JWT payload
$recruiter_id = $decoded['recruiter_id'] ?? $decoded['user_id'] ?? null;
$role = strtolower($decoded['role'] ?? '');

try {
    if ($role === 'admin') {
        // ✅ Admin can see all jobs
        $stmt = $conn->prepare("SELECT id, recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, created_at
                                FROM jobs 
                                ORDER BY created_at DESC");
    } else {
        // ✅ Recruiter can only see their jobs
        $stmt = $conn->prepare("SELECT id, recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, created_at
                                FROM jobs 
                                WHERE recruiter_id = ? 
                                ORDER BY created_at DESC");
        $stmt->bind_param("i", $recruiter_id);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $jobs = [];

        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }

        echo json_encode([
            "status" => true,
            "message" => "Jobs retrieved successfully",
            "data" => $jobs,
            "count" => count($jobs)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve jobs",
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
