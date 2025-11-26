<?php 
require_once '../cors.php';
require_once '../db.php';

try {

    // ---------------------------------------------------------
    // ✅ FIXED JWT – Always correct user_id and institute_id
    // ---------------------------------------------------------
    $decoded = authenticateJWT(['admin', 'institute']);

    $role = strtolower(trim($decoded['role'] ?? ''));

    if (!in_array($role, ['admin', 'institute'])) {
        echo json_encode(["status" => false, "message" => "Invalid role"]);
        exit;
    }

    // Correct user_id resolution
    $user_id = isset($decoded['user_id'])
        ? intval($decoded['user_id'])
        : (isset($decoded['id']) ? intval($decoded['id']) : 0);

    if ($user_id <= 0) {
        echo json_encode(["status" => false, "message" => "JWT user_id missing"]);
        exit;
    }

    // Load institute_id fresh each time
    $institute_id = 0;
    if ($role === 'institute') {
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();

        if ($institute_id <= 0) {
            echo json_encode(["status" => false, "message" => "Institute not found"]);
            exit;
        }
    }

    // ---------------------------------------------------------
    // 🔍 MAIN QUERY – STUDENT + COURSE + LATEST BATCH ASSIGNMENT
    // ---------------------------------------------------------
    $sql = "
        SELECT 
            sp.id AS student_id,
            u.user_name AS student_name,
            u.email,
            u.phone_number AS phone,
            sp.trade,
            sp.education,

            e.course_id,
            c.title AS course_title,
            e.enrollment_date,
            e.status AS enrollment_status,

            b.id AS batch_id,
            b.name AS batch_name,
            b.start_date,
            b.end_date
        FROM student_profiles sp
        INNER JOIN users u 
            ON sp.user_id = u.id

        -- 🔹 Course enrollments (same as before)
        LEFT JOIN student_course_enrollments e 
            ON sp.id = e.student_id
           AND (e.admin_action = 'approved' 
             OR e.admin_action = 'pending' 
             OR e.admin_action IS NULL)

        -- 🔹 Courses (filtered by institute for institute role)
        LEFT JOIN courses c 
            ON e.course_id = c.id
           " . ($role === 'institute' ? "AND c.institute_id = ?" : "") . "

        -- 🔹 LATEST batch assignment per student (from student_batches)
        LEFT JOIN student_batches sb
            ON sb.student_id = sp.id
           AND sb.admin_action = 'approved'
           AND sb.id = (
                SELECT sb2.id 
                FROM student_batches sb2 
                WHERE sb2.student_id = sp.id 
                  AND sb2.admin_action = 'approved'
                ORDER BY sb2.id DESC
                LIMIT 1
           )

        -- 🔹 Actual batch details (linked to course)
        LEFT JOIN batches b 
            ON b.id = sb.batch_id 
           AND b.admin_action = 'approved'
           " . ($role === 'institute' ? "AND b.course_id = c.id" : "") . "

        WHERE sp.deleted_at IS NULL 
          AND u.status = 'active'
          " . ($role === 'institute' ? "AND c.institute_id IS NOT NULL" : "") . "

        ORDER BY u.user_name ASC, c.title ASC
    ";

    if ($role === 'institute') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $institute_id);
    } else {
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    $seenCourses = [];
    $activeCount = 0;
    $completedCount = 0;

    while ($row = $result->fetch_assoc()) {

        if ($role === 'institute' && $row['course_id'] === null) {
            // Student not linked to this institute at all
            continue;
        }

        $sid = $row['student_id'];
        $cid = $row['course_id'] ?? 0;

        // Avoid duplicate same student+course rows
        if (isset($seenCourses[$sid][$cid])) continue;
        $seenCourses[$sid][$cid] = true;

        $statusVal = strtolower(trim($row['enrollment_status'] ?? 'enrolled'));
        if ($statusVal === 'enrolled') {
            $activeCount++;
        } elseif ($statusVal === 'completed') {
            $completedCount++;
        }

        $students[] = [
            "student_id"      => $sid,
            "name"            => $row['student_name'],
            "email"           => $row['email'],
            "phone"           => $row['phone'],
            "trade"           => $row['trade'],
            "education"       => $row['education'],
            "course_id"       => $row['course_id'],
            "course"          => $row['course_title'] ?? 'Not Assigned',
            "batch_id"        => $row['batch_id'],
            "batch"           => $row['batch_name'] ?? 'Not Assigned',
            "status"          => ucfirst($row['enrollment_status'] ?? 'Enrolled'),
            "enrollment_date" => $row['enrollment_date'] ?? null,
            "start_date"      => $row['start_date'] ?? null,
            "end_date"        => $row['end_date'] ?? null
        ];
    }

    // ---------------------------------------------------------
    // 🔢 Count total courses
    // ---------------------------------------------------------
    if ($role === 'institute') {
        $stmtC = $conn->prepare("
            SELECT COUNT(*) AS total_courses 
            FROM courses 
            WHERE institute_id = ?
              AND (admin_action = 'approved' 
               OR admin_action = 'pending' 
               OR admin_action IS NULL)
        ");
        $stmtC->bind_param("i", $institute_id);
    } else {
        $stmtC = $conn->prepare("
            SELECT COUNT(*) AS total_courses 
            FROM courses 
            WHERE admin_action = 'approved' 
               OR admin_action = 'pending' 
               OR admin_action IS NULL
        ");
    }

    $stmtC->execute();
    $resC = $stmtC->get_result();
    $totalCourses = ($resC->fetch_assoc())['total_courses'] ?? 0;

    echo json_encode([
        "status"  => true,
        "message" => "Student summary fetched successfully.",
        "role"    => $role,
        "summary" => [
            "total_students"    => count(array_unique(array_column($students, 'student_id'))),
            "active_students"   => $activeCount,
            "completed_students"=> $completedCount,
            "total_courses"     => $totalCourses
        ],
        "data" => $students
    ], JSON_PRETTY_PRINT);

    $stmt->close();
    $stmtC->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
