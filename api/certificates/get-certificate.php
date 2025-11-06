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

    // ✅ Optional: certificate ID filter
    $certificate_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // ✅ Base SQL (common for both)
    $sql = "
        SELECT 
            c.id AS certificate_id,
            c.file_url,
            c.issue_date,
            c.admin_action AS certificate_status,
            c.created_at,
            c.modified_at,
            s.id AS student_id,
            s.user_id,
            u.user_name AS student_name,
            u.email AS student_email,
            u.phone_number AS student_phone,
            co.id AS course_id,
            co.title AS course_title
        FROM certificates c
        INNER JOIN student_profiles s ON c.student_id = s.id
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN courses co ON c.course_id = co.id
        WHERE c.admin_action = 'approved'
    ";

    // ✅ Add WHERE condition if ID provided
    if ($certificate_id > 0) {
        $sql .= " AND c.id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $certificate_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql .= " ORDER BY c.id DESC";
        $result = $conn->query($sql);
    }

    // ✅ Process results
    $certificates = [];
    while ($row = $result->fetch_assoc()) {
        $absolute_path = __DIR__ . '/..' . $row['file_url'];
        if (!empty($row['file_url']) && !file_exists($absolute_path)) {
            $row['file_url'] = null;
        }

        $certificates[] = [
            "certificate_info" => [
                "certificate_id" => "CERT-" . date('Y') . "-" . str_pad($row['certificate_id'], 3, '0', STR_PAD_LEFT),
                "status" => ucfirst($row['certificate_status'] ?? "Pending"),
                "issue_date" => $row['issue_date'] ?: null,
                "file_url" => $row['file_url'],
                "created_at" => $row['created_at'],
                "modified_at" => $row['modified_at']
            ],
            "student_info" => [
                "student_id" => $row['student_id'],
                "name" => $row['student_name'],
                "email" => $row['student_email'],
                "phone" => $row['student_phone']
            ],
            "course_info" => [
                "course_id" => $row['course_id'],
                "course_name" => $row['course_title'] ?: "Not Assigned"
            ]
        ];
    }

    // ✅ Handle empty result
    if (empty($certificates)) {
        echo json_encode([
            "status" => false,
            "message" => ($certificate_id > 0)
                ? "Certificate not found"
                : "No certificates found"
        ]);
        exit;
    }

    // ✅ Response for single vs multiple
    if ($certificate_id > 0) {
        $data = $certificates[0]; // single certificate
        echo json_encode([
            "status" => true,
            "message" => "Certificate details fetched successfully",
            "data" => $data,
            "meta" => [
                "role" => $role,
                "timestamp" => date('Y-m-d H:i:s'),
                "api_version" => "1.0"
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            "status" => true,
            "message" => "Certificates fetched successfully",
            "data" => $certificates,
            "meta" => [
                "role" => $role,
                "total" => count($certificates),
                "timestamp" => date('Y-m-d H:i:s'),
                "api_version" => "1.0"
            ]
        ], JSON_PRETTY_PRINT);
    }

    if (isset($stmt)) $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
