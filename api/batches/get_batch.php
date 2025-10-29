<?php
// get_batch.php - Fetch all batches or specific batch with course & instructor info
require_once '../cors.php';
try {
    // ✅ Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role']);

    // ✅ Optional: batch_id from query
    $batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

    // -------------------------------
    // Build SQL base with JOINs
    // -------------------------------
    $sql = "
        SELECT 
            b.id AS batch_id,
            b.name AS batch_name,
            b.batch_time_slot,
            b.start_date,
            b.end_date,
            b.admin_action,
            c.id AS course_id,
            c.title AS course_title,
            f.id AS instructor_id,
            f.name AS instructor_name,
            f.email AS instructor_email,
            f.phone AS instructor_phone
        FROM batches b
        INNER JOIN courses c ON b.course_id = c.id
        INNER JOIN faculty_users f ON b.instructor_id = f.id
    ";

    // -------------------------------
    // Role-based filtering
    // -------------------------------
    if ($batch_id > 0) {
        $sql .= " WHERE b.id = ?";
    } elseif ($role === 'institute') {
        $sql .= " WHERE b.admin_action = 'approved'";
    }

    $sql .= " ORDER BY b.start_date DESC";

    // -------------------------------
    // Prepare and execute
    // -------------------------------
    $stmt = $conn->prepare($sql);

    if ($batch_id > 0) {
        $stmt->bind_param("i", $batch_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $batches = $result->fetch_all(MYSQLI_ASSOC);

    // -------------------------------
    // Response formatting
    // -------------------------------
    if ($batch_id > 0 && empty($batches)) {
        echo json_encode([
            "status" => false,
            "message" => "Batch not found or not accessible."
        ]);
        exit();
    }

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
