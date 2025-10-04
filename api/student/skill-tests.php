<?php
// skill-tests.php - List attempts & results for skill tests with admin_action filter
require_once '../cors.php';

// Authenticate JWT and get user info
$current_user = authenticateJWT(['admin', 'student']); 
// $current_user should have 'role' and 'id' (decoded JWT payload)

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => false, "message" => "Only GET requests allowed"]);
    exit;
}

// Optional filter: student_id
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

try {
    // Base SQL query
    $sql = "SELECT 
                id,
                student_id,
                test_platform,
                test_name,
                score,
                max_score,
                completed_at,
                badge_awarded,
                passed,
                admin_action,
                created_at,
                modified_at
            FROM skill_tests
            WHERE deleted_at IS NULL";

    $params = [];
    $types = "";

    // Role-based filter
    if ($current_user['role'] !== 'admin') {
        // Non-admin users can only see 'approved' records
        $sql .= " AND admin_action = 'approved'";
    } else {
        // Admin can see everything (pending + approved)
        if ($student_id) {
            $sql .= " AND student_id = ?";
            $types = "i";
            $params[] = $student_id;
        }
    }

    // If student_id is provided for non-admins, include it
    if ($student_id && $current_user['role'] !== 'admin') {
        $sql .= " AND student_id = ?";
        $types .= "i";
        $params[] = $student_id;
    }

    $sql .= " ORDER BY completed_at DESC";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $tests = [];
    while ($row = $result->fetch_assoc()) {
        $tests[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Skill test attempts fetched successfully",
        "count" => count($tests),
        "data" => $tests,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching skill tests",
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
