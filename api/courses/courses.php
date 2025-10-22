<?php
require_once '../cors.php';

try {
    // ✅ Authenticate user and role
    $user = authenticateJWT(['admin', 'institute', 'student']);

    // Extract token data safely
    $user_role = $user['role'] ?? 'student';
    $user_id   = $user['user_id'] ?? ($user['id'] ?? null);
    $institute_id = null;

    // ✅ For institute role
    if ($user_role === 'institute') {
        $institute_id = $user['institute_id'] ?? $user_id;
        if (!$institute_id) throw new Exception("Institute ID missing in token");
    }

    // ✅ Base query according to your current table
    $sql = "SELECT 
                id,
                institute_id,
                title,
                description,
                duration,
                fee,
                category_id,
                tagged_skills,
                batch_limit,
                status,
                instructor_name,
                mode,
                certification_allowed,
                module_title,
                module_description,
                media,
                admin_action,
                created_at,
                updated_at
            FROM courses
            WHERE 1=1";

    $params = [];
    $types = "";

    // ✅ Role-based visibility
    if ($user_role === 'admin') {
        // Admin sees all
    } elseif ($user_role === 'institute') {
        $sql .= " AND institute_id = ?";
        $params[] = $institute_id;
        $types .= "i";
    } else {
        // Students only see approved courses
        $sql .= " AND admin_action = ?";
        $params[] = 'approved';
        $types .= "s";
    }

    // ✅ Optional filter: specific institute (for admin)
    if (!empty($_GET['institute_id']) && $user_role === 'admin') {
        $sql .= " AND institute_id = ?";
        $params[] = intval($_GET['institute_id']);
        $types .= "i";
    }

    // ✅ Optional search (title/description/instructor)
    if (!empty($_GET['q'])) {
        $keyword = "%" . $_GET['q'] . "%";
        $sql .= " AND (title LIKE ? OR description LIKE ? OR instructor_name LIKE ?)";
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= "sss";
    }

    $sql .= " ORDER BY id DESC";

    // ✅ Prepare and execute query
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $result = $stmt->get_result();
    if (!$result) throw new Exception("Result fetch failed: " . $stmt->error);

    // ✅ Format results for frontend
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        if ($user_role !== 'admin') unset($row['admin_action']);
        $row['certification_allowed'] = (bool) $row['certification_allowed'];
        $row['fee'] = (float) $row['fee'];
        $row['duration'] = (int) $row['duration'];
        $courses[] = $row;
    }

    // ✅ Send response
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
