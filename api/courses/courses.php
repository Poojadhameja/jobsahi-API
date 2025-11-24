<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT
    $user = authenticateJWT(['admin', 'institute', 'student']);

    $user_role = strtolower($user['role'] ?? 'student');
    $user_id   = intval($user['user_id'] ?? ($user['id'] ?? 0));

    // ---------------------------------------------------------
    // 🔥 FIX: Institute ID must always be user_id from JWT
    // ---------------------------------------------------------
    if ($user_role === 'institute') {
        $institute_id = $user_id;   // FIXED
    } else {
        $institute_id = intval($user['institute_id'] ?? 0);
    }
    // ---------------------------------------------------------

    // ✅ Base query
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
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    // ✅ Role-based filters
    if ($user_role === 'admin') {
        // Admin sees all courses
    } elseif ($user_role === 'institute') {
        // Institute sees only its own courses
        if ($institute_id > 0) {
            $sql .= " AND c.institute_id = ?";
            $params[] = $institute_id;
            $types .= "i";
        }
    } else {
        // Student sees only approved courses
        $sql .= " AND c.admin_action = ?";
        $params[] = 'approved';
        $types .= "s";
    }

    // ✅ Optional filters
    if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected'])) {
        $sql .= " AND c.admin_action = ?";
        $params[] = $_GET['status'];
        $types .= "s";
    }

    if (!empty($_GET['category'])) {
        $sql .= " AND cc.category_name LIKE ?";
        $params[] = "%" . $_GET['category'] . "%";
        $types .= "s";
    }

    if (!empty($_GET['q'])) {
        $keyword = "%" . $_GET['q'] . "%";
        $sql .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.instructor_name LIKE ?)";
        $params = array_merge($params, [$keyword, $keyword, $keyword]);
        $types .= "sss";
    }

    $sql .= " ORDER BY c.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {

        if ($user_role === 'student') unset($row['admin_action']);

        $row['certification_allowed'] = (bool)$row['certification_allowed'];
        $row['fee'] = (float)$row['fee'];
        $row['category_name'] = $row['category_name'] ?? 'Technical';

        $courses[] = $row;
    }

    echo json_encode([
        "status" => true,
        "message" => "Courses retrieved successfully",
        "total_count" => count($courses),
        "user_role" => $user_role,
        "courses" => $courses
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage(),
        "courses" => []
    ], JSON_PRETTY_PRINT);
}
?>
