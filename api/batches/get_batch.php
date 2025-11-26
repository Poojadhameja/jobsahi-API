<?php
// get_batch.php - Fetch all batches or specific batch with course, instructor & students + batch_limit
require_once '../cors.php';
require_once '../db.php';

try {
    // 🔐 Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // 🔍 batch_id (optional)
    $batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

    // ===========================================
    // 1️⃣ BASE BATCH QUERY (batch_limit added)
    // ===========================================
    $sql = "
        SELECT 
            b.id AS batch_id,
            b.name AS batch_name,
            b.batch_time_slot,
            b.start_date,
            b.end_date,
            b.admin_action,
            b.course_id,

            c.title AS course_title,
            c.batch_limit,              -- ⭐ batch_limit yaha se aa raha hai

            b.instructor_id,
            f.name  AS instructor_name,
            f.email AS instructor_email,
            f.phone AS instructor_phone
        FROM batches b
        INNER JOIN courses c ON b.course_id = c.id
        LEFT JOIN faculty_users f ON b.instructor_id = f.id
    ";

    // Conditions
    $where = [];

    if ($batch_id > 0) {
        $where[] = "b.id = ?";
    } elseif ($role === 'institute') {
        // institute ko sirf approved batches
        $where[] = "b.admin_action = 'approved'";
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY b.start_date DESC";

    $stmt = $conn->prepare($sql);

    if ($batch_id > 0) {
        $stmt->bind_param("i", $batch_id);
    }

    $stmt->execute();
    $result   = $stmt->get_result();
    $batches  = $result->fetch_all(MYSQLI_ASSOC);

    // ===========================================
    // 2️⃣ SINGLE BATCH → ADD STUDENT LIST
    // ===========================================
    if ($batch_id > 0) {

        if (empty($batches)) {
            echo json_encode([
                "status"  => false,
                "message" => "Batch not found or not accessible."
            ], JSON_PRETTY_PRINT);
            exit;
        }

        $batch = $batches[0];

        // 🔍 Students in this batch
        $studentQuery = "
            SELECT 
                sp.id AS student_id,
                u.user_name   AS student_name,
                u.email       AS email,
                u.phone_number AS phone,
                e.status          AS enrollment_status,
                e.enrollment_date AS enrollment_date
            FROM student_batches sb
            INNER JOIN student_profiles sp ON sb.student_id = sp.id
            INNER JOIN users u ON sp.user_id = u.id          -- ✅ Yaha space fix kiya
            LEFT JOIN student_course_enrollments e 
                ON e.student_id = sp.id AND e.course_id = ?
            WHERE sb.batch_id = ?
            ORDER BY u.user_name ASC
        ";

        $stStmt = $conn->prepare($studentQuery);
        $stStmt->bind_param("ii", $batch['course_id'], $batch_id);
        $stStmt->execute();
        $studentsRes = $stStmt->get_result();

        $students = [];
        while ($row = $studentsRes->fetch_assoc()) {
            $students[] = [
                "student_id"       => intval($row['student_id']),
                "student_name"     => $row['student_name'],
                "email"            => $row['email'],
                "phone"            => $row['phone'],
                "enrollment_status"=> $row['enrollment_status'],
                "enrollment_date"  => $row['enrollment_date'],
            ];
        }

        echo json_encode([
            "status"        => true,
            "message"       => "Batch fetched successfully.",
            "batch"         => $batch,
            "batch_limit"   => intval($batch["batch_limit"]),   // ⭐ batch_limit return
            "students"      => $students,
            "student_count" => count($students)
        ], JSON_PRETTY_PRINT);

        $stStmt->close();
        $stmt->close();
        $conn->close();
        exit;
    }

    // ===========================================
    // 3️⃣ ALL BATCHES VIEW
    // ===========================================
    echo json_encode([
        "status"  => true,
        "role"    => $role,
        "count"   => count($batches),
        "batches" => $batches
    ], JSON_PRETTY_PRINT);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
