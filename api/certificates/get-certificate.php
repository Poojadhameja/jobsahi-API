<?php
// get-certificate.php – List / View certificates (GET only)
require_once '../cors.php';
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status"=>false,"message"=>"Only GET allowed"]);
    exit;
}

$decoded = authenticateJWT(['admin','institute','student']);
$role    = strtolower($decoded['role']);

// Optional filter ?id=
$certificate_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "
    SELECT 
        c.id AS certificate_id,
        c.file_url,
        c.issue_date,
        c.admin_action,
        c.created_at,
        u.user_name AS student_name,
        u.email,
        co.title AS course_title
    FROM certificates c
    JOIN student_profiles s ON c.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN courses co ON co.id = c.course_id
    WHERE c.admin_action = 'approved'
";

if ($certificate_id > 0) {
    $sql .= " AND c.id=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $sql .= " ORDER BY c.id DESC";
    $res = $conn->query($sql);
}

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        "certificate_id" => "CERT-".date('Y')."-".str_pad($row['certificate_id'],3,'0',STR_PAD_LEFT),
        "file_url"       => $row['file_url'],
        "issue_date"     => $row['issue_date'],
        "status"         => ucfirst($row['admin_action']),
        "student"        => [
            "name"  => $row['student_name'],
            "email" => $row['email'],
        ],
        "course"         => [
            "title" => $row['course_title']
        ]
    ];
}

echo json_encode([
    "status"=>true,
    "message"=>"Certificates fetched successfully",
    "data"=>$data,
    "count"=>count($data)
]);
exit;
?>