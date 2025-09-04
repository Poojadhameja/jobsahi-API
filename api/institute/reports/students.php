<?php
// students.php - Student analytics report with role-based access (JWT required)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
include __DIR__ . "/../../db.php";
require_once __DIR__ . "/../../jwt_token/jwt_helper.php";
require_once __DIR__ . "/../../auth/auth_middleware.php";

// Authenticate JWT - returns decoded payload
$decoded = authenticateJWT(['admin', 'recruiter', 'institute', 'student']); 

try {
    $role = $decoded['role']; // JWT role field

    // Role-based SQL filter for admin_action
    if ($role === 'admin') {
        // Admin sees both pending and approved
        $actionFilter = "admin_action IN ('pending', 'approval')";
    } else {
        // Other roles see only approved
        $actionFilter = "admin_action = 'approval'";
    }

    // Student analytics summary
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_students,
            COUNT(CASE WHEN gender='female' THEN 1 END) AS female_students,
            COUNT(CASE WHEN gender='male' THEN 1 END) AS male_students,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS new_today,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS new_this_week,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS new_this_month
        FROM student_profiles
        WHERE $actionFilter
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $analytics = $result->fetch_assoc();

    // Student distribution by job_type/trade
    $stmt2 = $conn->prepare("
        SELECT 
            trade AS category,
            COUNT(*) AS student_count
        FROM student_profiles
        WHERE trade IS NOT NULL AND $actionFilter
        GROUP BY trade
        ORDER BY student_count DESC
        LIMIT 10
    ");
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $category_distribution = [];
    while ($row = $result2->fetch_assoc()) {
        $category_distribution[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Student analytics retrieved successfully",
        "data" => [
            "summary" => $analytics,
            "category_distribution" => $category_distribution
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
