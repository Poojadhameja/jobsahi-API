<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate JWT for admin or institute
    $decoded = authenticateJWT(['admin', 'institute']);
    $role = strtolower($decoded['role'] ?? '');

    // ✅ Get user & institute info from JWT
    $user_id = intval($decoded['user_id'] ?? ($decoded['id'] ?? 0));
    $institute_id = 0;

    if ($role === 'institute') {
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $institute_id = intval($row['id']);
        }
        $stmt->close();

        if ($institute_id <= 0) {
            echo json_encode(["status" => false, "message" => "Institute not found for this user"]);
            exit;
        }
    }

    // ✅ Get student_id from URL
    $student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($student_id <= 0) {
        echo json_encode(["status" => false, "message" => "Valid student_id is required"]);
        exit;
    }

    // ✅ SQL Query to fetch detailed student info
    $sql = "
        SELECT 
            sp.id AS student_id,
            sp.user_id,
            u.user_name AS student_name,
            u.email,
            u.phone_number AS phone,
            sp.trade,
            sp.education,
            sp.skills,
            sp.resume,
            sp.certificates,
            sp.linkedin_url,
            sp.portfolio_link,
            sp.languages,
            sp.graduation_year,
            sp.cgpa,
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
        INNER JOIN users u ON sp.user_id = u.id
        LEFT JOIN student_course_enrollments e 
            ON sp.id = e.student_id AND e.admin_action = 'approved'
        LEFT JOIN courses c 
            ON e.course_id = c.id AND c.admin_action = 'approved'
        LEFT JOIN student_batches sb 
            ON sp.id = sb.student_id
        LEFT JOIN batches b 
            ON sb.batch_id = b.id AND b.admin_action = 'approved'
        WHERE sp.id = ?
          AND sp.deleted_at IS NULL
          AND u.status = 'active'
    ";

    if ($role === 'institute') {
        $sql .= " AND c.institute_id = ?";
    }

    $stmt = $conn->prepare($sql);
    if ($role === 'institute') {
        $stmt->bind_param("ii", $student_id, $institute_id);
    } else {
        $stmt->bind_param("i", $student_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $student = [
            "personal_info" => [
                "name"         => $row['student_name'],
                "email"        => $row['email'],
                "phone"        => $row['phone'],
                "trade"        => $row['trade'],
                "education"    => $row['education'],
                "languages"    => $row['languages'],
                "graduation_year" => $row['graduation_year'],
                "cgpa"         => $row['cgpa']
            ],
            "professional_info" => [
                "skills"       => $row['skills'],
                "course"       => $row['course_title'] ?: "Not Assigned",
                "batch"        => $row['batch_name'] ?: "Not Assigned",
                "status"       => ucfirst($row['enrollment_status'] ?: "Pending"),
                "enrollment_date" => $row['enrollment_date'],
                "course_duration" => $row['course_duration'],
                "start_date"   => $row['start_date'],
                "end_date"     => $row['end_date']
            ],
            "documents" => [
                "resume"       => $row['resume'],
                "certificates" => $row['certificates']
            ],
            "social_links" => [
                "linkedin_url"  => $row['linkedin_url'],
                "portfolio_link"=> $row['portfolio_link']
            ]
        ];

        echo json_encode([
            "status"  => true,
            "message" => "Student details fetched successfully.",
            "data"    => $student,
            "meta" => [
                "student_id" => $student_id,
                "role" => $role,
                "timestamp" => date('Y-m-d H:i:s')
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "No student found with given ID."
        ]);
    }

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
