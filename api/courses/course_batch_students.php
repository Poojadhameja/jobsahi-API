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
    // FETCH COURSES - Institute sees ALL their courses
    // ----------------------------------------------------
    if ($role === 'institute') {
        $course_sql = "SELECT id, title FROM courses WHERE institute_id = ?";
        if ($course_id > 0) {
            $course_sql .= " AND id = ?";
            $course_stmt = $conn->prepare($course_sql);
            $course_stmt->bind_param("ii", $institute_id, $course_id);
        } else {
            $course_stmt = $conn->prepare($course_sql);
            $course_stmt->bind_param("i", $institute_id);
        }
    } else {
        $course_sql = "SELECT id, title FROM courses WHERE admin_action = 'approved'";
        if ($course_id > 0) {
            $course_sql .= " AND id = ?";
            $course_stmt = $conn->prepare($course_sql);
            $course_stmt->bind_param("i", $course_id);
        } else {
            $course_stmt = $conn->prepare($course_sql);
        }
    }
    
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $response = [];

    while ($course = $course_result->fetch_assoc()) {
        $courseData = [
            "course_id" => intval($course['id']),
            "course_name" => $course['title'],
            "batches" => []
        ];

        // FETCH BATCHES - Institute sees all batches
        $batch_sql = "SELECT id, name, batch_time_slot, start_date, end_date FROM batches WHERE course_id = ?";
        if ($batch_id > 0) {
            $batch_sql .= " AND id = ?";
            $batch_stmt = $conn->prepare($batch_sql);
            $batch_stmt->bind_param("ii", $course['id'], $batch_id);
        } else {
            $batch_stmt = $conn->prepare($batch_sql);
            $batch_stmt->bind_param("i", $course['id']);
        }
        
        $batch_stmt->execute();
        $batch_result = $batch_stmt->get_result();

        while ($batch = $batch_result->fetch_assoc()) {

            $batchData = [
                "batch_id" => intval($batch['id']),
                "batch_name" => $batch['name'],
                "time_slot" => $batch['batch_time_slot'],
                "start_date" => $batch['start_date'],
                "end_date" => $batch['end_date'],
                "students" => []
            ];

            // FETCH STUDENTS - Simple query for institute
            $student_sql = "
                SELECT 
                    sp.id AS student_id,
                    u.user_name AS name,
                    u.email,
                    u.phone_number
                FROM student_batches sb
                JOIN student_profiles sp ON sb.student_id = sp.id
                JOIN users u ON sp.user_id = u.id
                WHERE sb.batch_id = ?
                  AND u.status = 'active'
            ";

            $student_stmt = $conn->prepare($student_sql);
            $student_stmt->bind_param("i", $batch['id']);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();

            while ($student = $student_result->fetch_assoc()) {
                $batchData['students'][] = [
                    "student_id" => intval($student['student_id']),
                    "name" => $student['name'],
                    "email" => $student['email'],
                    "phone_number" => $student['phone_number']
                ];
            }
            $student_stmt->close();

            $courseData['batches'][] = $batchData;
        }
        $batch_stmt->close();
        
        // ✅ Add courseData to response array
        $response[] = $courseData;
    }
    $course_stmt->close();

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
