<?php
// skill-tests.php - List attempts & results for skill tests with admin_action filter
require_once '../cors.php';

$current_user = authenticateJWT(['admin', 'student']);
$user_role = $current_user['role'] ?? '';
$user_id   = $current_user['user_id'] ?? null;

header('Content-Type: application/json');

function respond($data){
    echo json_encode($data);
    exit;
}

/* ============================
   READ (GET)
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

    try {
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

        // Role-based filtering
        if ($user_role === 'student') {
            // Students can only see approved records
            $sql .= " AND admin_action = 'approved'";
            
            // If student_id provided, filter by it
            if ($student_id) {
                $sql .= " AND student_id = ?";
                $types = "i";
                $params[] = $student_id;
            }
        } else if ($user_role === 'admin') {
            // Admin can see all records (pending + approved)
            if ($student_id) {
                $sql .= " AND student_id = ?";
                $types = "i";
                $params[] = $student_id;
            }
        }

        $sql .= " ORDER BY completed_at DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) respond(["status"=>false,"message"=>"Prepare failed: ".$conn->error]);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $tests = [];
        while ($row = $result->fetch_assoc()) {
            $tests[] = $row;
        }

        respond([
            "status" => true,
            "message" => "Skill test attempts fetched successfully",
            "count" => count($tests),
            "data" => $tests,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        respond(["status"=>false,"message"=>$e->getMessage()]);
    }
}

// Invalid request method
respond(["status"=>false,"message"=>"Only GET requests allowed"]);
?>