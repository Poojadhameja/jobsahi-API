<?php
// get-course.php — Fetch single course details with correct instructor (course-level)
require_once '../cors.php';
require_once '../db.php';

try {

    // ---------------------------------------------------------
    // 🔐 AUTH USER
    // ---------------------------------------------------------
    $decoded = authenticateJWT(['admin', 'institute', 'student']);

    $user_role = strtolower($decoded['role'] ?? 'student');
    $user_id   = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));
    $institute_id = intval($decoded['institute_id'] ?? 0);

    // ---------------------------------------------------------
    // 🏫 FIX INSTITUTE ID FOR INSTITUTE USER
    // ---------------------------------------------------------
    if ($user_role === 'institute') {

        if ($institute_id <= 0) {
            $stmt = $conn->prepare("
                SELECT id 
                FROM institute_profiles 
                WHERE user_id = ? 
                LIMIT 1
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $institute_id = intval($res['id'] ?? 0);
        }
    }

    // ---------------------------------------------------------
    // 📌 COURSE ID REQUIRED
    // ---------------------------------------------------------
    $course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($course_id <= 0) {
        throw new Exception("Invalid or missing course ID");
    }

    // ---------------------------------------------------------
    // 📄 FETCH COURSE DATA (INCLUDES instructor_id)
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
            c.mode,
            c.certification_allowed,
            c.module_title,
            c.module_description,
            c.media,
            c.fee,
            c.instructor_name,   -- 🔥 IMPORTANT
            c.created_at,
            c.updated_at,
            ip.institute_name
        FROM courses AS c
        LEFT JOIN course_category AS cc 
            ON c.category_id = cc.id 
        LEFT JOIN institute_profiles AS ip 
            ON c.institute_id = ip.id
        WHERE c.id = ?
    ";

    $params = [$course_id];
    $types  = "i";

    // Institute-specific restriction
    if ($user_role === "institute") {
        $sql .= " AND c.institute_id = ?";
        $params[] = $institute_id;
        $types .= "i";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$course) {
        throw new Exception("Course not found");
    }

    // ---------------------------------------------------------
    // 👨‍🏫 GET INSTRUCTOR FROM faculty_users TABLE
    // (Instructor assigned during course creation → courses.instructor_id)
    // ---------------------------------------------------------
    $instructor_block = [
        "instructor_id"    => null,
        "instructor_name"  => null,
        "instructor_email" => null,
        "instructor_phone" => null
    ];

    if (!empty($course['instructor_id'])) {

        $stmtI = $conn->prepare("
            SELECT id, name, email, phone 
            FROM faculty_users 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmtI->bind_param("i", $course['instructor_id']);
        $stmtI->execute();
        $ins = $stmtI->get_result()->fetch_assoc();
        $stmtI->close();

        if ($ins) {
            $instructor_block = [
                "instructor_id"    => $ins['id'],
                "instructor_name"  => $ins['name'],
                "instructor_email" => $ins['email'],
                "instructor_phone" => $ins['phone']
            ];
        }
    }

    // ---------------------------------------------------------
    // 🖼 MEDIA PARSING
    // ---------------------------------------------------------
    $BASE_PATH = "http://localhost/jobsahi-API/api/uploads/institute_course_image/";
    
    $media_raw = $course['media'];
    $media_urls = [];

    if (!empty($media_raw)) {

        if (str_starts_with($media_raw, '[')) {
            $decoded = json_decode($media_raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $m) {
                    $clean = str_replace("uploads/institute_course_image/", "", $m);
                    $media_urls[] = $BASE_PATH . $clean;
                }
            }
        } else {
            $clean = str_replace("uploads/institute_course_image/", "", $media_raw);
            $media_urls[] = $BASE_PATH . $clean;
        }
    }

    $course['media_urls'] = $media_urls;

    // ---------------------------------------------------------
    // ADD INSTRUCTOR BLOCK TO RESPONSE
    // ---------------------------------------------------------
    $course['instructor'] = $instructor_block;

    // ---------------------------------------------------------
    // TYPE FIXING
    // ---------------------------------------------------------
    $course['fee'] = (float)$course['fee'];
    $course['certification_allowed'] = (bool)$course['certification_allowed'];
    $course['category_name'] = $course['category_name'] ?? "Technical";

    // ---------------------------------------------------------
    // ✅ GET FEEDBACK STATISTICS FOR THIS COURSE
    // ---------------------------------------------------------
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
    $course['average_rating'] = $feedback_data['avg_rating'] !== null 
        ? round((float)$feedback_data['avg_rating'], 2) 
        : 0.00;
    $course['total_feedback_count'] = intval($feedback_data['total_feedback_count'] ?? 0);
    
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
    
    $course['rating_distribution'] = $rating_distribution;

    // ---------------------------------------------------------
    // RESPONSE
    // ---------------------------------------------------------
    echo json_encode([
        "status" => true,
        "message" => "Course retrieved successfully",
        "course" => $course
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
?>
