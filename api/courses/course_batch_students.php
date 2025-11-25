<?php
// get_course_batch_students.php
require_once '../cors.php';
require_once '../db.php';

try {
    // ----------------------------------------------------
    // ✅ Authenticate JWT (admin or institute)
    // ----------------------------------------------------
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));

    // ----------------------------------------------------
    // ✅ Determine institute_id (valid for institute login)
    // ----------------------------------------------------
    $institute_id = 0;

    if ($role === 'institute') {
        $stmt = $conn->prepare("
            SELECT id 
            FROM institute_profiles 
            WHERE user_id = ? LIMIT 1
        ");
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
                "message" => "Invalid institute login. No institute profile found."
            ]);
            exit;
        }
    }

    // ----------------------------------------------------
    // Optional filters
    // ----------------------------------------------------
    $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
    $batch_id  = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

    // ----------------------------------------------------
    // FINAL CONDITION FOR INSTITUTE FILTER
    // ----------------------------------------------------
    $INSTITUTE_FILTER = ($role === 'institute') 
        ? " AND institute_id = $institute_id " 
        : "";

    // ----------------------------------------------------
    // Fetch courses (ONLY approved + institute filter)
    // ----------------------------------------------------
    $course_query = "
        SELECT id, title 
        FROM courses 
        WHERE admin_action = 'approved'
        $INSTITUTE_FILTER
    ";

    if ($course_id > 0) {
        $course_query .= " AND id = $course_id ";
    }

    $course_result = $conn->query($course_query);
    $response = [];

    while ($course = $course_result->fetch_assoc()) {

        $courseData = [
            "course_id" => intval($course['id']),
            "course_name" => $course['title'],
            "batches" => []
        ];

        // ----------------------------------------------------
        // Fetch batches (ONLY approved + institute filter)
        // ----------------------------------------------------
        $batch_query = "
            SELECT id, name, batch_time_slot, start_date, end_date 
            FROM batches 
            WHERE course_id = {$course['id']} 
              AND admin_action = 'approved'
              $INSTITUTE_FILTER
        ";

        if ($batch_id > 0) {
            $batch_query .= " AND id = $batch_id ";
        }

        $batch_result = $conn->query($batch_query);

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
            // Fetch students (only students of this institute)
            // ----------------------------------------------------
            $student_query = "
                SELECT 
                    sp.id AS student_id,
                    u.user_name AS name,
                    u.email,
                    u.phone_number
                FROM student_profiles sp
                JOIN users u ON sp.user_id = u.id
                JOIN student_batches sb ON sp.id = sb.student_id
                JOIN student_course_enrollments sce ON sp.id = sce.student_id
                WHERE sb.batch_id = {$batch['id']}
                  AND sce.course_id = {$course['id']}
                  AND sb.admin_action = 'approved'
                  AND sce.admin_action = 'approved'
                  $INSTITUTE_FILTER
            ";

            $student_result = $conn->query($student_query);

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
        "message" => "Course → Batch → Student hierarchy fetched successfully",
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
