<?php
// create_course.php - Create new course (Admin, Institute access)
require_once '../cors.php';

// Authenticate JWT and allow multiple roles
$decoded = authenticateJWT(['admin', 'institute']); 
$user_role = $decoded['role'] ?? '';  
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

    // Get JSON data from frontend
    $data = json_decode(file_get_contents("php://input"), true);

    // Extract all fields safely
    $title        = trim($data['title'] ?? '');
    $description = trim(strip_tags($data['description'] ?? ''));
    $duration     = trim($data['duration'] ?? '');
    $fee          = floatval($data['fee'] ?? 0);
    $category     = trim($data['category'] ?? '');
    $tagged_skills = trim($data['tagged_skills'] ?? '');
    $batch_limits = intval($data['batch_limits'] ?? 0);
    $course_status = trim($data['course_status'] ?? 'Active');
    $instructor_name = trim($data['instructor_name'] ?? '');
    $mode          = trim($data['mode'] ?? '');
    $certification_allowed = isset($data['certification_allowed']) && $data['certification_allowed'] ? 1 : 0;

    $admin_action = 'pending'; // Default status when new course is created
    $institute_id = ($user_role === 'institute') ? $user_id : 0;

    // Validation
    if (
        empty($title) || empty($description) || empty($duration) || 
        empty($category) || empty($instructor_name) || empty($mode) ||
        $fee <= 0 || $batch_limits <= 0
    ) {
        echo json_encode([
            "status" => false,
            "message" => "All required fields must be filled."
        ]);
        exit();
    }

    try {
        // ✅ Insert query with all fields
        $stmt = $conn->prepare("
            INSERT INTO courses (
                institute_id, title, description, duration, fee, category, 
                tagged_skills, batch_limits, course_status, instructor_name, 
                mode, certification_allowed, admin_action
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "issdsssisssis",
            $institute_id, $title, $description, $duration, $fee, $category,
            $tagged_skills, $batch_limits, $course_status, $instructor_name,
            $mode, $certification_allowed, $admin_action
        );

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
            $sql = "SELECT * FROM courses ORDER BY id DESC";
        } elseif ($user_role === 'institute') {
            // Institute sees their own courses
            $sql = "SELECT * FROM courses WHERE institute_id = ? ORDER BY id DESC";
        } else {
            // Students and others see only approved courses
            $sql = "SELECT * FROM courses WHERE admin_action = 'approved' ORDER BY id DESC";
        }

        if ($user_role === 'institute') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

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
