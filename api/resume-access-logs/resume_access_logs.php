<?php
// resume_access_logs.php - Track resume views (GET /api/v1/resume-access-logs)
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

// ✅ Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'recruiter', 'student']); // Allow all authenticated users

// ✅ Extract user_id & role from decoded JWT
$user_id = $decoded['user_id'];
$user_role = $decoded['role'];

try {
    if ($user_role === 'admin') {
        // ✅ Admin can see all logs
        $stmt = $conn->prepare("
            SELECT ral.id, ral.recruiter_id, ral.student_id, ral.viewed_at,
                   r.user_name AS recruiter_name, r.email AS recruiter_email,
                   s.user_name AS student_name, s.email AS student_email
            FROM resume_access_logs ral
            LEFT JOIN users r ON ral.recruiter_id = r.id
            LEFT JOIN users s ON ral.student_id = s.id
            ORDER BY ral.viewed_at DESC
        ");
        $stmt->execute();

    } elseif ($user_role === 'recruiter') {
        // ✅ Recruiter can only see logs they created
        $stmt = $conn->prepare("
            SELECT ral.id, ral.recruiter_id, ral.student_id, ral.viewed_at,
                   s.user_name AS student_name, s.email AS student_email
            FROM resume_access_logs ral
            LEFT JOIN users s ON ral.student_id = s.id
            WHERE ral.recruiter_id = ?
            ORDER BY ral.viewed_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

    } elseif ($user_role === 'student') {
        // ✅ Student can only see who viewed their resume
        $stmt = $conn->prepare("
            SELECT ral.id, ral.recruiter_id, ral.student_id, ral.viewed_at,
                   r.user_name AS recruiter_name, r.email AS recruiter_email
            FROM resume_access_logs ral
            LEFT JOIN users r ON ral.recruiter_id = r.id
            WHERE ral.student_id = ?
            ORDER BY ral.viewed_at DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }

    $result = $stmt->get_result();
    $logs = [];

    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Resume access logs retrieved successfully",
        "data" => $logs,
        "total_records" => count($logs)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
