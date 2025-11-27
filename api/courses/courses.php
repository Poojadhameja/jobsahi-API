<?php 
require_once '../cors.php';
require_once '../db.php';

try {

    // ---------------------------------------------------------
    // 🔥 FINAL FIX — CORRECT JWT + INSTITUTE ID DETECTION
    // ---------------------------------------------------------
    $decoded = authenticateJWT(['admin', 'institute', 'student']);

    $user_role = strtolower($decoded['role'] ?? 'student');
    $user_id   = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // If logged in as institute → fetch actual institute_id from DB
    if ($user_role === 'institute') {
        $stmtX = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
        $stmtX->bind_param("i", $user_id);
        $stmtX->execute();
        $resX = $stmtX->get_result()->fetch_assoc();
        $stmtX->close();

        $institute_id = intval($resX['id'] ?? 0);

    } else {
        // For admin/student → use whatever JWT contains (unchanged)
        $institute_id = intval($decoded['institute_id'] ?? 0);
    }
    // ---------------------------------------------------------


    // ---------------------------------------------------------
    // BASE QUERY (admin_action removed)
    // ---------------------------------------------------------
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
            c.created_at,
            c.updated_at,
            ip.institute_name
        FROM courses AS c
        LEFT JOIN course_category AS cc ON c.category_id = cc.id
        LEFT JOIN institute_profiles AS ip ON c.institute_id = ip.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";


    // ---------------------------------------------------------
    // ROLE FILTERS (admin_action removed)
    // ---------------------------------------------------------
    if ($user_role === 'admin') {
        // Admin sees all — no filter
    } 
    elseif ($user_role === 'institute') {
        if ($institute_id > 0) {
            $sql .= " AND c.institute_id = ?";
            $params[] = $institute_id;
            $types .= "i";
        }
    } 
    else {
        // Student — earlier filter removed (admin_action removed)
        // Students now see all courses
    }


    // ---------------------------------------------------------
    // OPTIONAL FILTERS (admin_action removed)
    // ---------------------------------------------------------
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


    // ---------------------------------------------------------
    // EXECUTE QUERY
    // ---------------------------------------------------------
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) $stmt->bind_param($types, ...$params);

    $stmt->execute();
    $result = $stmt->get_result();


    // ---------------------------------------------------------
    // FORMAT OUTPUT + ADD media_url
    // ---------------------------------------------------------
    $BASE_URL = "http://localhost/jobsahi-API/api/uploads/institute_course_image/";

    $courses = [];
    while ($row = $result->fetch_assoc()) {

        $row['certification_allowed'] = (bool)$row['certification_allowed'];
        $row['fee'] = (float)$row['fee'];
        $row['category_name'] = $row['category_name'] ?? 'Technical';

        // MEDIA URL FIX
        if (!empty($row['media'])) {
            if (strpos($row['media'], 'uploads/') !== false) {
                $row['media_url'] = $BASE_URL . $row['media'];
            } else {
                $row['media_url'] = $BASE_URL . "uploads/" . $row['media'];
            }
        } else {
            $row['media_url'] = "";
        }

        $courses[] = $row;
    }


    // ---------------------------------------------------------
    // FINAL RESPONSE
    // ---------------------------------------------------------
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
