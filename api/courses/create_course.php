<?php
// create_course.php - Create new course (Admin, Institute access)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../db.php';
require_once '../jwt_token/jwt_helper.php';
require_once '../auth/auth_middleware.php';

// Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'institute']); 
$user_role = $decoded['role'] ?? '';  // assuming 'role' exists in JWT
$user_id   = $decoded['user_id'] ?? 0;

// ---------- POST: Create Course (Admin / Institute only) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!in_array($user_role, ['admin', 'institute'])) {
        echo json_encode([
            "status" => false,
            "message" => "Unauthorized: Only admin or institute can create courses"
        ]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $title       = isset($data['title']) ? trim($data['title']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $duration    = isset($data['duration']) ? trim($data['duration']) : '';
    $fee         = isset($data['fee']) ? floatval($data['fee']) : 0;
    $admin_action = 'pending'; // New courses default to 'pending'

    if (empty($title) || empty($description) || empty($duration) || $fee <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "All fields are required"
        ]);
        exit();
    }

    $institute_id = $user_role === 'institute' ? $user_id : 0; // admin may not have institute_id

    try {
        $stmt = $conn->prepare("INSERT INTO courses (institute_id, title, description, duration, fee, admin_action) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdss", $institute_id, $title, $description, $duration, $fee, $admin_action);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => true,
                "message" => "Course created successfully",
                "course_id" => $stmt->insert_id
            ]);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Failed to create course",
                "error"   => $stmt->error
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => false,
            "message" => "Error: " . $e->getMessage()
        ]);
    }

    $conn->close();
    exit();
}

// ---------- GET: List Courses with Role-based Visibility ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($user_role === 'admin') {
            // Admin sees everything
            $sql = "SELECT * FROM courses";
        } else {
            // Other roles see only approved courses
            $sql = "SELECT * FROM courses WHERE admin_action = 'approved'";
        }

        $result = $conn->query($sql);
        $courses = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
        }

        echo json_encode([
            "status" => true,
            "courses" => $courses
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status" => false,
            "message" => "Error: " . $e->getMessage()
        ]);
    }

    $conn->close();
    exit();
}
?>
