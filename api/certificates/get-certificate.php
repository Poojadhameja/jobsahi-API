<?php
require_once '../cors.php';
require_once '../db.php';

try {
    // ✅ Authenticate (Admin, Institute, or Student)
    $decoded = authenticateJWT(['admin', 'institute', 'student']);
    $role = strtolower($decoded['role'] ?? '');

    // ✅ Allow only GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(["status" => false, "message" => "Only GET method allowed"]);
        exit;
    }

    // ✅ Validate certificate ID
    $certificate_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($certificate_id <= 0) {
        echo json_encode(["status" => false, "message" => "Certificate ID is required"]);
        exit;
    }

    // ✅ Fetch certificate and related data
    $sql = "
        SELECT 
            c.id AS certificate_id,
            c.file_url,
            c.issue_date,
            c.admin_action AS certificate_status,
            s.id AS student_id,
            s.user_id,
            u.user_name AS student_name,
            u.email AS student_email,
            u.phone_number AS student_phone,
            co.id AS course_id,
            co.title AS course_title,
            sb.batch_id,
            b.name AS batch_name
        FROM certificates c
        INNER JOIN student_profiles s ON c.student_id = s.id
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN courses co ON c.course_id = co.id
        LEFT JOIN student_batches sb ON s.id = sb.student_id
        LEFT JOIN batches b ON sb.batch_id = b.id
        WHERE c.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // ✅ Return the file_url only if it exists in the folder, else null
        $absolute_path = __DIR__ . '/..' . $row['file_url'];
        if (!empty($row['file_url']) && !file_exists($absolute_path)) {
            $row['file_url'] = null;
        }

        // ✅ Build and send response
        echo json_encode([
            "status" => true,
            "message" => "Certificate details fetched successfully",
            "data" => [
                "certificate_info" => [
                    "certificate_id" => "CERT-" . date('Y') . "-" . str_pad($row['certificate_id'], 3, '0', STR_PAD_LEFT),
                    "status" => ucfirst($row['certificate_status'] ?? "Pending"),
                    "issue_date" => $row['issue_date'] ?: null,
                    "file_url" => $row['file_url']
                ],
                "student_info" => [
                    "student_id" => $row['student_id'],
                    "name" => $row['student_name'],
                    "email" => $row['student_email'],
                    "phone" => $row['student_phone']
                ],
                "course_info" => [
                    "course_id" => $row['course_id'],
                    "course_name" => $row['course_title'] ?: "Not Assigned",
                    "batch_name" => $row['batch_name'] ?: "Not Assigned"
                ]
            ],
            "meta" => [
                "role" => $role,
                "timestamp" => date('Y-m-d H:i:s'),
                "api_version" => "1.0"
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(["status" => false, "message" => "Certificate not found"]);
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
