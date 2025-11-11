<?php
// get-course.php — Fetch single course details with category name and role-based filters
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: application/json');

try {
    // ✅ Authenticate user
    $user = authenticateJWT(['admin', 'institute', 'student']);

    $user_role = strtolower($user['role'] ?? 'student');
    $user_id   = intval($user['user_id'] ?? ($user['id'] ?? 0));
    $institute_id = intval($user['institute_id'] ?? 0);

    // ✅ Course ID (required)
    $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($course_id <= 0) {
        throw new Exception("Invalid or missing course ID");
    }

    // ✅ Base query (with category join)
    $sql = "
        SELECT 
            c.id,
            c.institute_id,
            c.title,
            c.description,
            c.duration,
            c.category_id,
            cc.category_name,
            c.tagged_skills,
            c.batch_limit,
            c.status,
            c.instructor_name,
            c.mode,
            c.certification_allowed,
            c.module_title,
            c.module_description,
            c.media,
            c.fee,
            c.admin_action,
            c.created_at,
            c.updated_at
        FROM courses AS c
        LEFT JOIN course_category AS cc ON c.category_id = cc.id
        WHERE c.id = ?
    ";

    $params = [$course_id];
    $types  = "i";

    // ✅ Role-based visibility
    if ($user_role === 'admin') {
        // Admin: no filter, can view any course
    } elseif ($user_role === 'institute') {
        // Institute: can only view own courses
        $sql .= " AND c.institute_id = ?";
        $params[] = $institute_id ?: $user_id;
        $types .= "i";
    } else {
        // Student: only approved courses
        $sql .= " AND c.admin_action = 'approved'";
    }

    // ✅ Prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // ✅ Type normalization
        $row['certification_allowed'] = (bool) $row['certification_allowed'];
        $row['fee'] = (float) $row['fee'];
        $row['category_name'] = $row['category_name'] ?? 'Technical';

        // ✅ Remove sensitive fields for students
        if ($user_role === 'student') {
            unset($row['admin_action']);
        }

        echo json_encode([
            "status" => true,
            "message" => "Course retrieved successfully",
            "user_role" => $user_role,
            "course" => $row
        ], JSON_PRETTY_PRINT);
    } else {
        // ✅ Custom role-based message
        $msg = match ($user_role) {
            'admin' => "Course not found.",
            'institute' => "Course not found or not owned by this institute.",
            default => "Course not found or not approved yet."
        };
        http_response_code(404);
        echo json_encode([
            "status" => false,
            "message" => $msg
        ], JSON_PRETTY_PRINT);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
