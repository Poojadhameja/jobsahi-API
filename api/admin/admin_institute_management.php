<?php
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate Admin or Institute
$decoded = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role']);
$user_id   = intval($decoded['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["success" => false, "message" => "Only GET method allowed"]);
    exit;
}

// ✅ Fetch institute_id if user is institute
$institute_id = null;
if ($user_role === 'institute') {
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($institute_id);
    $stmt->fetch();
    $stmt->close();
}

/* ============================================================
   1️⃣ COURSE & ENROLLMENT MONITORING
   ============================================================ */

$whereInstitute = $institute_id ? "WHERE c.institute_id = $institute_id" : "";

$queryCourses = "
    SELECT 
        c.title AS course_name,
        cc.category_name AS category,
        c.description AS description,
        COUNT(DISTINCT sce.student_id) AS enrolled,
        CASE WHEN c.certification_allowed = 1 THEN 'Active' ELSE 'Inactive' END AS certificate
    FROM courses c
    LEFT JOIN course_category cc ON c.category_id = cc.id
    LEFT JOIN student_course_enrollments sce ON c.id = sce.course_id
    $whereInstitute
    GROUP BY c.title, cc.category_name, c.description, c.certification_allowed
    ORDER BY MAX(c.created_at) DESC
";

$resultCourses = $conn->query($queryCourses);
$courseList = $resultCourses ? $resultCourses->fetch_all(MYSQLI_ASSOC) : [];

/* ------------------------------------------------------------
   ⭐ REMOVE DUPLICATE COURSES SAFELY WITHOUT ANY LOGIC CHANGE
   ------------------------------------------------------------ */
$uniqueCourses = [];
$finalCourseList = [];

foreach ($courseList as $c) {
    if (!in_array($c['course_name'], $uniqueCourses)) {
        $uniqueCourses[] = $c['course_name'];
        $finalCourseList[] = $c;
    }
}

/* ============================================================
   1B️⃣ ENROLLMENT TRENDS
   ============================================================ */

$queryTrends = "
    SELECT 
        DATE_FORMAT(sce.created_at, '%b') AS month,
        COUNT(*) AS value
    FROM student_course_enrollments sce
    INNER JOIN courses c ON sce.course_id = c.id
    " . ($institute_id ? "WHERE c.institute_id = $institute_id" : "") . "
    GROUP BY month
    ORDER BY MONTH(sce.created_at)
";

$resultTrends = $conn->query($queryTrends);
$enrollmentTrends = $resultTrends ? $resultTrends->fetch_all(MYSQLI_ASSOC) : [];

/* ============================================================
   2️⃣ PLACEMENT READY STUDENTS
   ============================================================ */

$queryPlacement = "
    SELECT 
        u.user_name AS student_name,
        c.title AS course_name,
        j.title AS job_title,
        rp.company_name AS company_name,
        DATE_FORMAT(MAX(i.scheduled_at), '%d %b, %Y') AS placement_drive,
        CASE 
            WHEN a.status = 'selected' THEN 'Placed'
            WHEN a.status = 'shortlisted' THEN 'Shortlisted'
            WHEN a.status = 'rejected' THEN 'Rejected'
            ELSE 'Applied'
        END AS status
    FROM applications a
    INNER JOIN student_profiles sp ON a.student_id = sp.id
    INNER JOIN users u ON sp.user_id = u.id
    LEFT JOIN jobs j ON a.job_id = j.id
    LEFT JOIN recruiter_profiles rp ON j.recruiter_id = rp.id
    LEFT JOIN interviews i ON i.application_id = a.id
    LEFT JOIN student_course_enrollments sce ON sp.id = sce.student_id
    LEFT JOIN courses c ON sce.course_id = c.id
";

if ($institute_id) {
    $queryPlacement .= " WHERE c.institute_id = $institute_id";
}

$queryPlacement .= "
    GROUP BY a.id
    ORDER BY i.scheduled_at DESC
";

$resultPlacement = $conn->query($queryPlacement);
$placementReady = $resultPlacement ? $resultPlacement->fetch_all(MYSQLI_ASSOC) : [];

/* ============================================================
   FINAL RESPONSE
   ============================================================ */

echo json_encode([
    "success" => true,
    "data" => [
        "course_enrollment" => [
            "course_list" => $finalCourseList,  // ⭐ now unique!
            "enrollment_trends" => $enrollmentTrends
        ],
        "placement_ready_students" => $placementReady
    ]
]);
?>
