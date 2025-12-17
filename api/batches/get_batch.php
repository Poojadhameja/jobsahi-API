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

    // Get institute_id for filtering (admin impersonation support)
    $institute_id = 0;
    if ($role === 'admin') {
        // Admin impersonation: Get institute_id from request parameters
        $provided_id = 0;
        if (isset($_GET['institute_id']) && !empty($_GET['institute_id'])) {
            $provided_id = intval($_GET['institute_id']);
        } elseif (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
            $provided_id = intval($_GET['user_id']);
        } elseif (isset($_GET['uid']) && !empty($_GET['uid'])) {
            $provided_id = intval($_GET['uid']);
        } elseif (isset($_GET['instituteId']) && !empty($_GET['instituteId'])) {
            $provided_id = intval($_GET['instituteId']);
        }

        if ($provided_id > 0) {
            // Try to find institute_id - first check if it's already an institute_profiles.id
            $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $provided_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $inst = $res->fetch_assoc();
            $stmt->close();

            if ($inst) {
                // Found by institute_profiles.id
                $institute_id = intval($inst['id']);
            } else {
                // Not found by id, try to find by user_id (convert user_id to institute_id)
                $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
                $stmt->bind_param("i", $provided_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $inst = $res->fetch_assoc();
                $stmt->close();

                if ($inst) {
                    // Found by user_id
                    $institute_id = intval($inst['id']);
                }
                // If not found, $institute_id remains 0 (admin can see all batches)
            }
        }
    } elseif ($role === 'institute') {
        // Get institute_id from logged-in user
        $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();
    }

    // Conditions
    $where = [];

    if ($batch_id > 0) {
        $where[] = "b.id = ?";
    } elseif ($role === 'institute' || ($role === 'admin' && $institute_id > 0)) {
        // Institute or admin with institute_id: filter by institute and approved batches
        $where[] = "c.institute_id = ?";
        $where[] = "b.admin_action = 'approved'";
    } elseif ($role === 'institute') {
        // institute ko sirf approved batches (fallback)
        $where[] = "b.admin_action = 'approved'";
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY b.start_date DESC";

    $stmt = $conn->prepare($sql);

    // Bind parameters
    if ($batch_id > 0) {
        $stmt->bind_param("i", $batch_id);
    } elseif (($role === 'institute' || ($role === 'admin' && $institute_id > 0)) && $institute_id > 0) {
        $stmt->bind_param("i", $institute_id);
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
        $active_students = 0;
        $completed_students = 0;
        $total_students = 0;

        while ($row = $studentsRes->fetch_assoc()) {
            $total_students++;
            $status = strtolower(trim($row['enrollment_status'] ?? 'enrolled'));
            
            // Count active students (status = 'enrolled')
            if ($status === 'enrolled') {
                $active_students++;
            }
            // Count completed students (status = 'completed')
            if ($status === 'completed') {
                $completed_students++;
            }

            $students[] = [
                "student_id"       => intval($row['student_id']),
                "student_name"     => $row['student_name'],
                "email"            => $row['email'],
                "phone"            => $row['phone'],
                "enrollment_status"=> $row['enrollment_status'],
                "enrollment_date"  => $row['enrollment_date'],
            ];
        }

        // Calculate completion percentage
        $completion_percent = 0;
        if ($total_students > 0) {
            $completion_percent = round(($completed_students / $total_students) * 100, 2);
        }

        echo json_encode([
            "status"            => true,
            "message"           => "Batch fetched successfully.",
            "batch"             => $batch,
            "batch_limit"       => intval($batch["batch_limit"]),
            "students"          => $students,
            "student_count"     => $total_students,
            "active_students"   => $active_students,
            "completion_percent"=> $completion_percent
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
