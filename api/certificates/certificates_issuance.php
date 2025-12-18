<?php
// certificates_issuance.php – List / View certificates
require_once '../cors.php';
require_once '../db.php';

// Authenticate both admin + institute
$decoded = authenticateJWT(['admin', 'institute']);
$userRole = strtolower($decoded['role']);
$userId   = intval($decoded['user_id']);

$institute_id = 0;

// ------------------------------------------------------------
// Get institute_id if institute
// ------------------------------------------------------------
if ($userRole === 'institute') {

    $stmt = $conn->prepare("
        SELECT id FROM institute_profiles 
        WHERE user_id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $institute_id = intval($res['id'] ?? 0);

    if ($institute_id <= 0) {
        echo json_encode(["status" => false, "message" => "Institute not found"]);
        exit;
    }
}

// ------------------------------------------------------------
// CHECK IF SINGLE CERTIFICATE REQUEST
// ------------------------------------------------------------
$certificateId = isset($_GET['id']) ? intval($_GET['id']) : 0;


// ------------------------------------------------------------
// MEDIA HELPERS (R2 only - no local handling)
// ------------------------------------------------------------
function getMediaUrl($fileName) {
    if (empty($fileName)) return null;
    // Return R2 URL as-is (already stored in database)
    return $fileName;
}


// ------------------------------------------------------------
// BASE SQL (with template joins)
// ------------------------------------------------------------
$baseSQL = "
SELECT 
    c.id AS certificate_id,
    c.file_url,
    c.issue_date,
    c.admin_action AS status,

    sp.id AS student_profile_id,
    u.user_name AS student_name,
    u.email AS student_email,
    u.phone_number AS phone_number,

    cr.id AS course_id,
    cr.title AS course_title,

    ip.institute_name,

    b.name AS batch_name,

    ct.description AS template_description,
    ct.logo,
    ct.seal,
    ct.signature

FROM certificates c
JOIN student_profiles sp ON sp.id = c.student_id
JOIN users u ON u.id = sp.user_id
JOIN courses cr ON cr.id = c.course_id
JOIN institute_profiles ip ON ip.id = cr.institute_id
LEFT JOIN student_batches sb ON sb.student_id = sp.id
LEFT JOIN batches b ON b.id = sb.batch_id

LEFT JOIN certificate_templates ct 
    ON ct.id = c.certificate_template_id
";


// ============================================================
// 1️⃣ FETCH SINGLE CERTIFICATE BY ID (WITH description + media)
// ============================================================

if ($certificateId > 0) {

    if ($userRole === 'institute') {
        $sql = $baseSQL . " 
            WHERE c.id = ? AND cr.institute_id = ? 
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $certificateId, $institute_id);

    } else {
        // admin
        $sql = $baseSQL . " WHERE c.id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $certificateId);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {

        echo json_encode([
            "status" => true,
            "message" => "Certificate record found",
            "data" => [
                "certificate_id"       => $row["certificate_id"],
                "file_url"             => $row["file_url"],
                "student_name"         => $row["student_name"],
                "student_email"        => $row["student_email"],
                "phone_number"         => $row["phone_number"],
                "institute_name"       => $row["institute_name"],
                "course_title"         => $row["course_title"],
                "batch_name"           => $row["batch_name"],
                "issue_date"           => $row["issue_date"],
                "template_description" => $row["template_description"],

                // ADDED MEDIA ONLY HERE ✔
                "template_logo"        => getMediaUrl($row["logo"]),
                "template_seal"        => getMediaUrl($row["seal"]),
                "template_signature"   => getMediaUrl($row["signature"]),

                "status"               => $row["status"]
            ]
        ]);
        exit;
    }

    echo json_encode(["status" => false, "message" => "Certificate not found"]);
    exit;
}



// ============================================================
// 2️⃣ FETCH ALL CERTIFICATES (LIST MODE)
// ============================================================

$listSQL = "
SELECT DISTINCT
    c.id AS certificate_id,
    c.issue_date,
    c.admin_action AS status,

    u.user_name AS student_name,

    cr.title AS course_title,
    ip.institute_name,
    b.name AS batch_name
FROM certificates c
JOIN student_profiles sp ON sp.id = c.student_id
JOIN users u ON u.id = sp.user_id
JOIN courses cr ON cr.id = c.course_id
JOIN institute_profiles ip ON ip.id = cr.institute_id
LEFT JOIN student_batches sb ON sb.student_id = sp.id
LEFT JOIN batches b ON b.id = sb.batch_id
";

if ($userRole === 'institute') {
    $listSQL .= " WHERE cr.institute_id = ? ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($listSQL);
    $stmt->bind_param("i", $institute_id);

} else {
    $listSQL .= " ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($listSQL);
}

$stmt->execute();
$result = $stmt->get_result();

$output = [];

while ($row = $result->fetch_assoc()) {

    $output[] = [
        "certificate_id" => $row["certificate_id"],
        "student_name"   => $row["student_name"],
        "institute_name" => $row["institute_name"],
        "course_title"   => $row["course_title"],
        "batch_name"     => $row["batch_name"],
        "issue_date"     => $row["issue_date"],
        "status"         => $row["status"]
    ];
}

echo json_encode([
    "status" => true,
    "message" => "Certificate issuance list fetched",
    "count" => count($output),
    "data" => $output
]);


?>
