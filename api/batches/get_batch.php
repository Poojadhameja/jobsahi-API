<?php
// get_batch.php - Fetch all batches or specific batch with course & instructor info
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // ✅ Optional: batch_id from query
    $batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

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
            b.instructor_id,
            f.name AS instructor_name,
            f.email AS instructor_email,
            f.phone AS instructor_phone
        FROM batches b
        INNER JOIN courses c ON b.course_id = c.id
        LEFT JOIN faculty_users f ON b.instructor_id = f.id
    ";

    // Role / filter conditions
    $where = [];

    if ($batch_id > 0) {
        $where[] = "b.id = ?";
    } elseif ($role === 'institute') {
        // sirf approved batches dikhana (jo tum pehle hi chahte the)
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
    $result = $stmt->get_result();
    $batches = $result->fetch_all(MYSQLI_ASSOC);

    if ($batch_id > 0) {
        if (!empty($batches)) {
            echo json_encode([
                "status" => true,
                "message" => "Batch fetched successfully.",
                "batch" => $batches[0]
            ], JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                "status" => false,
                "message" => "Batch not found or not accessible."
            ], JSON_PRETTY_PRINT);
        }
    } else {
        echo json_encode([
            "status"  => true,
            "role"    => $role,
            "count"   => count($batches),
            "batches" => $batches
        ], JSON_PRETTY_PRINT);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "status"  => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
