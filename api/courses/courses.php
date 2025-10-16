<?php
// courses.php – Get course list with role-based visibility
require_once '../cors.php';

try {
    // 🔐 Authenticate user and determine role
    $user = authenticateJWT(['admin', 'institute', 'student']);
    $user_role = $user['role'] ?? 'student';
    $user_id   = $user['user_id'] ?? ($user['id'] ?? null);
    $institute_id = null;

    // If logged in as institute, capture institute_id from token
    if ($user_role === 'institute') {
        $institute_id = $user['institute_id'] ?? $user_id;
        if (!$institute_id) {
            throw new Exception("Institute ID missing in token");
        }
    }

    // ---------- Base Query ----------
    $sql = "
        SELECT 
            id, 
            institute_id, 
            course_code, 
            title, 
            description, 
            course_type, 
            level, 
            credits, 
            duration, 
            fee, 
            target_skills, 
            teacher_name, 
            min_students, 
            max_students, 
            start_date, 
            end_date, 
            registration_start_date, 
            registration_end_date, 
            grading_criteria, 
            office_hours, 
            office_location, 
            exam_details, 
            button_allowing_level, 
            faqs, 
            subject_title, 
            module_description, 
            media_path, 
            is_certification_based, 
            status, 
            admin_action, 
            created_at, 
            updated_at
        FROM courses 
        WHERE 1=1
    ";

    $params = [];
    $types  = "";

    // ---------- Role-Based Visibility ----------
    if ($user_role === 'admin') {
        // Admin can see all courses (no filter)
    } elseif ($user_role === 'institute') {
        // Institute sees only their own courses
        $sql .= " AND institute_id = ?";
        $params[] = $institute_id;
        $types   .= "i";
    } else {
        // Students / public can only see approved courses
        $sql .= " AND admin_action = ?";
        $params[] = 'approved';
        $types   .= "s";
    }

    // ---------- Optional Filters ----------
    if (!empty($_GET['institute_id']) && $user_role !== 'institute') {
        $sql .= " AND institute_id = ?";
        $params[] = intval($_GET['institute_id']);
        $types   .= "i";
    }

    if (!empty($_GET['min_fee']) && is_numeric($_GET['min_fee'])) {
        $sql .= " AND fee >= ?";
        $params[] = floatval($_GET['min_fee']);
        $types   .= "d";
    }

    if (!empty($_GET['max_fee']) && is_numeric($_GET['max_fee'])) {
        $sql .= " AND fee <= ?";
        $params[] = floatval($_GET['max_fee']);
        $types   .= "d";
    }

    if (!empty($_GET['duration'])) {
        $sql .= " AND duration = ?";
        $params[] = $_GET['duration'];
        $types   .= "s";
    }

    if (!empty($_GET['q'])) {
        $sql .= " AND (title LIKE ? OR description LIKE ? OR course_code LIKE ?)";
        $keyword = "%" . $_GET['q'] . "%";
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types   .= "sss";
    }

    $sql .= " ORDER BY created_at DESC";

    // ---------- Prepare and Execute ----------
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $courses = [];

    while ($row = $result->fetch_assoc()) {
        // For non-admins, hide internal moderation state
        if ($user_role !== 'admin') {
            unset($row['admin_action']);
        }
        $courses[] = $row;
    }

    // ---------- Output ----------
    echo json_encode([
        "status" => true,
        "message" => "Courses retrieved successfully",
        "courses" => $courses,
        "total_count" => count($courses),
        "user_role" => $user_role,
        "institute_id_used" => $institute_id
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error retrieving courses: " . $e->getMessage(),
        "courses" => []
    ]);

    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>
