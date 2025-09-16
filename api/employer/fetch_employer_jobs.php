<?php
include '../CORS.php';

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter']); 
$user_role = $decoded['role'] ?? '';

// Get recruiter ID from URL parameter
$recruiter_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($recruiter_id)) {
    echo json_encode([
        "status" => false,
        "message" => "Recruiter ID is required"
    ]);
    exit();
}

try {
    // ✅ Build query with role-based visibility
    if ($user_role === 'admin') {
        // Admin sees both pending + approval
        $sql = "SELECT recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, admin_action, created_at 
                FROM jobs 
                WHERE recruiter_id = ?";
    } else {
        // Recruiter, Institute, Student see only approval
        $sql = "SELECT recruiter_id, title, description, location, skills_required, salary_min, salary_max, job_type, experience_required, application_deadline, is_remote, no_of_vacancies, status, admin_action, created_at 
                FROM jobs 
                WHERE recruiter_id = ? AND admin_action = 'approval'";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $recruiter_id);
    
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
