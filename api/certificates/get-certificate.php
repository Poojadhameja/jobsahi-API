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

// Helper function to get media URL (R2 only - no local handling)
function getMediaURL($file) {
    if (empty($file)) return null;
    
    // Return R2 URL as-is (already stored in database)
    return $file;
}

$sql = "
    SELECT 
        c.id AS certificate_id,
        c.file_url,
        c.issue_date,
        c.admin_action,
        c.created_at,
        c.certificate_template_id,
        u.user_name AS student_name,
        u.email,
        co.title AS course_title,
        ct.logo AS template_logo,
        ct.seal AS template_seal,
        ct.signature AS template_signature
    FROM certificates c
    JOIN student_profiles s ON c.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN courses co ON co.id = c.course_id
    LEFT JOIN certificate_templates ct ON ct.id = c.certificate_template_id
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
        ],
        "template"       => [
            "logo"      => getMediaURL($row['template_logo']),
            "seal"      => getMediaURL($row['template_seal']),
            "signature" => getMediaURL($row['template_signature'])
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