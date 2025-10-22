<?php
require_once '../cors.php';

try {
    // ✅ Authenticate user and role
    $user = authenticateJWT(['admin', 'institute', 'student']);

    // Safely extract values from token
    $user_role = $user['role'] ?? 'student';
    $user_id = $user['user_id'] ?? ($user['id'] ?? null);
    $institute_id = null;

    // ✅ Institute users
    if ($user_role === 'institute') {
        $institute_id = $user['institute_id'] ?? $user_id;
        if (!$institute_id) throw new Exception("Institute ID missing in token");
    }

    // ✅ Base query
    $sql = "SELECT 
                id,
                institute_id,
                title,
                description,
                duration,
                fee,
                category,
                tagged_skills,
                batch_limits,
                course_status,
                instructor_name,
                mode,
                certification_allowed,
                admin_action
            FROM courses
            WHERE 1=1";

    $params = [];
    $types = "";

    // ✅ Role-based filters
    if ($user_role === 'admin') {
        // admin sees all
    } elseif ($user_role === 'institute') {
        $sql .= " AND institute_id = ?";
        $params[] = $institute_id;
        $types .= "i";
    } else {
        $sql .= " AND admin_action = ?";
        $params[] = 'approved';
        $types .= "s";
    }

    // ✅ Optional filters (frontend can pass)
    if (!empty($_GET['institute_id']) && $user_role !== 'institute') {
        $sql .= " AND institute_id = ?";
        $params[] = intval($_GET['institute_id']);
        $types .= "i";
    }

    if (!empty($_GET['q'])) {
        $sql .= " AND (title LIKE ? OR description LIKE ? OR category LIKE ?)";
        $keyword = "%" . $_GET['q'] . "%";
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= "sss";
    }

    $sql .= " ORDER BY id DESC";

    // ✅ Prepare & execute
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $result = $stmt->get_result();
    if (!$result) throw new Exception("Result fetch failed: " . $stmt->error);

    // ✅ Format results
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        if ($user_role !== 'admin') unset($row['admin_action']);
        $row['certification_allowed'] = boolval($row['certification_allowed']);
        $row['fee'] = floatval($row['fee']);
        $row['duration'] = intval($row['duration']);
        $courses[] = $row;
    }

    // ✅ Response
    echo json_encode([
        "status" => true,
        "message" => "Courses retrieved successfully",
        "courses" => $courses,
        "total_count" => count($courses),
        "user_role" => $user_role,
        "token_user_id" => $user_id,
        "institute_id_used" => $institute_id
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage(),
        "courses" => []
    ]);
}
?>
