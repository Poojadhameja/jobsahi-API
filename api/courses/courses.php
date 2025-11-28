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
    // FORMAT OUTPUT + ADD media_url + FEEDBACK DATA
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

        // ✅ GET FEEDBACK STATISTICS FOR THIS COURSE
        $course_id = intval($row['id']);
        
        // Get average rating and total feedback count (all feedbacks - no admin_action filter)
        $feedback_sql = "
            SELECT 
                AVG(rating) AS avg_rating,
                COUNT(id) AS total_feedback_count
            FROM course_feedback
            WHERE course_id = ?
        ";
        
        $feedback_stmt = $conn->prepare($feedback_sql);
        $feedback_stmt->bind_param("i", $course_id);
        $feedback_stmt->execute();
        $feedback_result = $feedback_stmt->get_result();
        $feedback_data = $feedback_result->fetch_assoc();
        $feedback_stmt->close();
        
        // Add feedback statistics to course data
        $row['average_rating'] = $feedback_data['avg_rating'] !== null 
            ? round((float)$feedback_data['avg_rating'], 2) 
            : 0.00;
        $row['total_feedback_count'] = intval($feedback_data['total_feedback_count'] ?? 0);
        
        // Get rating distribution (1-5 stars)
        $rating_dist_sql = "
            SELECT rating, COUNT(*) AS count
            FROM course_feedback
            WHERE course_id = ?
            GROUP BY rating
            ORDER BY rating DESC
        ";
        
        $rating_dist_stmt = $conn->prepare($rating_dist_sql);
        $rating_dist_stmt->bind_param("i", $course_id);
        $rating_dist_stmt->execute();
        $rating_dist_result = $rating_dist_stmt->get_result();
        
        $rating_distribution = [
            '5' => 0,
            '4' => 0,
            '3' => 0,
            '2' => 0,
            '1' => 0
        ];
        
        while ($rating_row = $rating_dist_result->fetch_assoc()) {
            $rating_distribution[strval($rating_row['rating'])] = intval($rating_row['count']);
        }
        $rating_dist_stmt->close();
        
        $row['rating_distribution'] = $rating_distribution;

        $courses[] = $row;
    }

    // Close main statement
    $stmt->close();

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
