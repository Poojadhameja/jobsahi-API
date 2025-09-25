<?php
// get_job_applications.php - List job applicants (Role-based visibility)
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

// ✅ Authenticate JWT (allow admin, recruiter)
$decoded = authenticateJWT(['admin', 'recruiter']); 
$user_role = $decoded['role']; // role from JWT payload

// Get job ID from URL parameter
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id <= 0) {
    echo json_encode([
        "status" => false,
        "message" => "Invalid job ID"
    ]);
    exit();
}

try {
    // ✅ Build query conditionally based on role
    if ($user_role === 'admin') {
        // Admin sees ALL (pending + approved)
        $query = "SELECT * FROM applications WHERE job_id = ? ORDER BY applied_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $job_id);
    } else {
        // Recruiter, Institute, Students → Only approved applications
        $query = "SELECT * FROM applications WHERE job_id = ? AND admin_action = 'approved' ORDER BY applied_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $job_id);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $applications = [];

        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }

        echo json_encode([
            "status" => true,
            "message" => "Job applications retrieved successfully",
            "role" => $user_role,
            "job_id" => $job_id,
            "applications" => $applications,
            "total_applications" => count($applications)
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Failed to retrieve job applications",
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
