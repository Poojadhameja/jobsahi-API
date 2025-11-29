<?php
// get_certificate_templates.php - List + Fetch by ID (Institute / Admin Valid Only)
require_once '../cors.php';
require_once '../db.php';

// ----------------------------------------------
// 🔐 AUTHENTICATE JWT
// ----------------------------------------------
$decoded  = authenticateJWT(['admin', 'institute']);
$userRole = strtolower($decoded['role'] ?? '');
$userId   = intval($decoded['user_id'] ?? 0);

// ----------------------------------------------
// 🎯 FIX: GET institute_id PROPERLY
// ----------------------------------------------
$institute_id = 0;

if ($userRole === 'institute') {
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$profile) {
        echo json_encode(["status" => false, "message" => "Institute profile not found"]);
        exit;
    }

    $institute_id = intval($profile['id']);
}

// ----------------------------------------------
// 📌 ALLOW SINGLE TEMPLATE FETCH BY ID
// ----------------------------------------------
$templateId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// BASE SQL WITHOUT admin_action
$baseSelect = "
    SELECT 
        id,
        institute_id,
        template_name,
        logo,
        seal,
        signature,
        description,
        is_active,
        created_at,
        modified_at,
        deleted_at
    FROM certificate_templates
";

// ----------------------------------------------
// 🌐 Helper to generate media URL
// ----------------------------------------------
function mediaURL($file) {
    if (!$file) return null;

    $file = str_replace([
        "../", "./", 
        "/uploads/institute_certificate_templates/"
    ], "", $file);

    $fullPath = __DIR__ . '/../uploads/institute_certificate_templates/' . $file;

    if (!file_exists($fullPath)) return null;

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host     = $_SERVER['HTTP_HOST'];

    return $protocol . $host . "/jobsahi-API/api/uploads/institute_certificate_templates/" . $file;
}

// ----------------------------------------------
// 1️⃣ FETCH SINGLE TEMPLATE
// ----------------------------------------------
if ($templateId > 0) {

    if ($userRole === 'admin') {
        $sql = $baseSelect . " WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $templateId);

    } elseif ($userRole === 'institute') {
        $sql = $baseSelect . " WHERE id = ? AND institute_id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $templateId, $institute_id);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(["status" => false, "message" => "Template not found"]);
        exit;
    }

    echo json_encode([
        "status" => true,
        "message" => "Certificate template found",
        "data" => [
            "id"            => $row["id"],
            "institute_id"  => $row["institute_id"],
            "template_name" => $row["template_name"],
            "description"   => $row["description"],
            "is_active"     => (bool)$row["is_active"],

            // Media
            "logo"      => mediaURL($row["logo"]),
            "seal"      => mediaURL($row["seal"]),
            "signature" => mediaURL($row["signature"]),
        ]
    ]);
    exit;
}

// ----------------------------------------------
// 2️⃣ FETCH ALL TEMPLATES
// ----------------------------------------------
if ($userRole === 'admin') {
    $sql = $baseSelect . " WHERE deleted_at IS NULL ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);

} elseif ($userRole === 'institute') {
    $sql = $baseSelect . " WHERE deleted_at IS NULL AND institute_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $institute_id);
}

$stmt->execute();
$result = $stmt->get_result();

$templates = [];

while ($row = $result->fetch_assoc()) {
    $templates[] = [
        "id"            => $row["id"],
        "institute_id"  => $row["institute_id"],
        "template_name" => $row["template_name"],
        "description"   => $row["description"],
        "is_active"     => (bool)$row["is_active"],
        "created_at"    => $row["created_at"],
        "modified_at"   => $row["modified_at"],

        // Media
        "logo"      => mediaURL($row["logo"]),
        "seal"      => mediaURL($row["seal"]),
        "signature" => mediaURL($row["signature"]),
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    "status" => true,
    "message" => "Certificate templates fetched successfully",
    "count" => count($templates),
    "data"  => $templates
]);
?>
