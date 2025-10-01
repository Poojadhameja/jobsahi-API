<?php
// get_jobs_by_role.php - List jobs/applications based on admin_action and user role
require_once '../cors.php';

// ✅ Authenticate JWT and allow all roles
$decoded = authenticateJWT(['admin', 'recruiter']); // returns array
$role = strtolower($decoded['role'] ?? '');

// Build SQL based on role
try {
    if ($role === 'admin') {
        // Admin sees all records
        $sql = "SELECT id, recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, admin_action, created_at
                FROM jobs
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
    } else {
        // Others only see approved jobs
        $sql = "SELECT id, recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, admin_action, created_at
                FROM jobs
                WHERE admin_action = 'approved'
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
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
