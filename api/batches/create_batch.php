<?php
// create_batch.php - Create new batch (Admin, Institute access) & List batches based on admin_action
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

try {
    // Authenticate JWT for multiple roles
    $decoded = authenticateJWT(['admin', 'institute']); // returns array with role info
    $role = $decoded['role']; // role of the logged-in user

    // -------------------------------
    // Handle GET request - List batches
    // -------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        if ($role === 'admin') {
            // Admin sees all batches
            $sql = "SELECT * FROM batches";
            $stmt = $conn->prepare($sql);
        } else {
            // Other roles see only approved batches
            $sql = "SELECT * FROM batches WHERE admin_action = 'approved'";
            $stmt = $conn->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $batches = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            "status" => true,
            "role"   => $role,
            "batches" => $batches
        ]);
        $stmt->close();
        $conn->close();
        exit();
    }

    // -------------------------------
    // Handle POST request - Create batch
    // -------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Only admin or institute can create batches
        if (!in_array($role, ['admin', 'institute'])) {
            echo json_encode([
                "status" => false,
                "message" => "Unauthorized. Only admin or institute can create batches."
            ]);
            exit();
        }

        // Get POST data
        $data = json_decode(file_get_contents("php://input"), true);

        $course_id     = isset($data['course_id']) ? (int)$data['course_id'] : 0;
        $name          = isset($data['name']) ? trim($data['name']) : '';
        $start_date    = isset($data['start_date']) ? $data['start_date'] : null;
        $end_date      = isset($data['end_date']) ? $data['end_date'] : null;
        $instructor_id = isset($data['instructor_id']) ? (int)$data['instructor_id'] : null;
        $admin_action  = "pending"; // default value

        // Validate course exists
        $check = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check->bind_param("i", $course_id);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows === 0) {
            echo json_encode([
                "status" => false,
                "message" => "Invalid course_id. Course does not exist."
            ]);
            exit();
        }
        $check->close();

        // Insert batch
        $stmt = $conn->prepare("INSERT INTO batches (course_id, name, start_date, end_date, instructor_id, admin_action) 
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $course_id, $name, $start_date, $end_date, $instructor_id, $admin_action);

        if ($stmt->execute()) {
            echo json_encode([
                "status"   => true,
                "message"  => "Batch created successfully",
                "batch_id" => $stmt->insert_id
            ]);
        } else {
            echo json_encode([
                "status"  => false,
                "message" => "Failed to create batch",
                "error"   => $stmt->error
            ]);
        }

        $stmt->close();
        $conn->close();
        exit();
    }

    // Invalid method
    echo json_encode([
        "status" => false,
        "message" => "Invalid request method."
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
