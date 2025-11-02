<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // -------------------------------
    // ✅ Determine institute_id from JWT (automatic)
    // -------------------------------
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));
    $institute_id = 0;

    if ($role === 'institute') {
        // Fetch institute_id from institute_profiles linked to this user
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();
    } elseif ($role === 'admin') {
        $institute_id = 0; // Admin can view all
    }

    // ❌ Stop if institute_id not found for institute role
    if ($role === 'institute' && $institute_id <= 0) {
        echo json_encode([
            "status" => false,
            "message" => "Institute ID missing or invalid in token"
        ]);
        exit;
    }

    // -------------------------------
    // ✅ Build the SQL query
    // -------------------------------
    $sql = "
        SELECT 
            sp.id AS student_id,
            sp.user_id,
            u.user_name AS student_name,
            u.email,
            u.phone_number AS phone,
            sp.trade,
            sp.education,
            e.status AS enrollment_status,
            e.enrollment_date,
            c.id AS course_id,
            c.title AS course_title,
            c.duration AS course_duration,
            sb.batch_id,
            b.name AS batch_name,
            b.start_date,
            b.end_date
        FROM student_profiles sp
        INNER JOIN users u 
            ON sp.user_id = u.id
        INNER JOIN student_course_enrollments e 
            ON sp.id = e.student_id 
            AND e.admin_action = 'approved' 
            AND e.status IN ('enrolled', 'completed')
        INNER JOIN courses c 
            ON e.course_id = c.id 
            AND c.admin_action = 'approved'
            " . ($role === 'institute' ? "AND c.institute_id = ?" : "") . "
        LEFT JOIN student_batches sb
            ON sp.id = sb.student_id
        LEFT JOIN batches b 
            ON sb.batch_id = b.id 
            AND b.admin_action = 'approved'
        WHERE sp.deleted_at IS NULL
          AND u.status = 'active'
        ORDER BY u.user_name ASC
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

    while ($row = $result->fetch_assoc()) {
        $students[] = [
            "student_id"       => $row['student_id'],
            "name"             => $row['student_name'],
            "email"            => $row['email'],
            "phone"            => $row['phone'],
            "trade"            => $row['trade'],
            "education"        => $row['education'],
            "course"           => $row['course_title'] ?? "Not Assigned",
            "batch"            => $row['batch_name'] ?? "Not Assigned",
            "status"           => ucfirst($row['enrollment_status'] ?? "Unknown"),
            "enrollment_date"  => $row['enrollment_date'] ?? null,
            "start_date"       => $row['start_date'] ?? null,
            "end_date"         => $row['end_date'] ?? null
        ];
    }

    // -------------------------------
    // ✅ Return Response
    // -------------------------------
    echo json_encode([
        "status" => true,
        "message" => "Enrolled students fetched successfully.",
        "role" => $role,
        "count" => count($students),
        "data" => $students
    ], JSON_PRETTY_PRINT);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
