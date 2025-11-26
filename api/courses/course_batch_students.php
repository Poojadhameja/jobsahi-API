<?php
// get_course_batch_students.php
require_once '../cors.php';
require_once '../db.php';

try {
    // ----------------------------------------------------
    // Authenticate JWT (admin or institute)
    // ----------------------------------------------------
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? 0);

    // ----------------------------------------------------
    // Detect institute_id ONLY from JWT
    // ----------------------------------------------------
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
            echo json_encode([
                "status" => false,
                "message" => "Invalid institute login. Institute profile missing."
            ]);
            exit;
        }
    }

    // Input Filters
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $batch_id  = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

    // ----------------------------------------------------
    // APPLY FILTER ONLY ON COURSES (REAL FIX)
    // ----------------------------------------------------
    $COURSE_FILTER = ($role === 'institute') 
        ? " AND c.institute_id = $institute_id "
        : "";

    // ----------------------------------------------------
    // FETCH COURSES
    // ----------------------------------------------------
    $course_sql = "
        SELECT c.id, c.title
        FROM courses c
        WHERE c.admin_action = 'approved'
        $COURSE_FILTER
    ";

    if ($course_id > 0) {
        $course_sql .= " AND c.id = $course_id ";
    }

    $course_result = $conn->query($course_sql);
    $response = [];

    while ($course = $course_result->fetch_assoc()) {

        $courseData = [
            "course_id" => intval($course['id']),
            "course_name" => $course['title'],
            "batches" => []
        ];

        // ----------------------------------------------------
        // FETCH BATCHES OF THIS COURSE
        // ----------------------------------------------------
        $batch_sql = "
            SELECT id, name, batch_time_slot, start_date, end_date
            FROM batches
            WHERE course_id = {$course['id']}
              AND admin_action = 'approved'
        ";

        if ($batch_id > 0) {
            $batch_sql .= " AND id = $batch_id ";
        }

        $batch_result = $conn->query($batch_sql);

        while ($batch = $batch_result->fetch_assoc()) {

            $batchData = [
                "batch_id" => intval($batch['id']),
                "batch_name" => $batch['name'],
                "time_slot" => $batch['batch_time_slot'],
                "start_date" => $batch['start_date'],
                "end_date" => $batch['end_date'],
                "students" => []
            ];

            // ----------------------------------------------------
            // FETCH STUDENTS OF THIS BATCH & COURSE
            // ----------------------------------------------------
            $student_sql = "
                SELECT 
                    sp.id AS student_id,
                    u.user_name AS name,
                    u.email,
                    u.phone_number
                FROM student_batches sb
                JOIN student_profiles sp ON sb.student_id = sp.id
                JOIN users u ON sp.user_id = u.id
                JOIN student_course_enrollments sce 
                    ON sce.student_id = sp.id
                WHERE sb.batch_id = {$batch['id']}
                  AND sce.course_id = {$course['id']}
                  AND sb.admin_action = 'approved'
                  AND sce.admin_action = 'approved'
            ";

            $student_result = $conn->query($student_sql);

            while ($student = $student_result->fetch_assoc()) {
                $batchData['students'][] = [
                    "student_id" => intval($student['student_id']),
                    "name" => $student['name'],
                    "email" => $student['email'],
                    "phone_number" => $student['phone_number']
                ];
            }

            $courseData['batches'][] = $batchData;
        }

        $response[] = $courseData;
    }

    echo json_encode([
        "status" => true,
        "message" => "Course → Batch → Students fetched successfully",
        "data" => $response
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>
