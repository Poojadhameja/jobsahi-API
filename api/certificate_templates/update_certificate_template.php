<?php
// update_certificate_template.php - Update existing certificate template
require_once '../cors.php';
require_once '../db.php';

// -------------------------------------------------------------
// ALLOW POST + PUT
// -------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['PUT', 'POST'])) {
    echo json_encode(["status" => false, "message" => "Invalid request method"]);
    exit;
}

// -------------------------------------------------------------
// AUTHENTICATE USER
// -------------------------------------------------------------
$decoded   = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role'] ?? '');
$user_id   = intval($decoded['user_id'] ?? 0);

// -------------------------------------------------------------
// FETCH INSTITUTE ID
// -------------------------------------------------------------
$institute_id = 0;

if ($user_role === 'institute') {
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $institute_id = intval($res->fetch_assoc()['id']);
    } else {
        echo json_encode(["status" => false, "message" => "Institute profile not found"]);
        exit;
    }
    $stmt->close();

} elseif ($user_role === 'admin') {

    // For admin → get institute_id from body (not URL)
    if ($method === "POST") {
        $institute_id = intval($_POST['institute_id'] ?? 0);
    }
}

// -------------------------------------------------------------
// INPUT PARSING (same system as recruiter API)
// -------------------------------------------------------------
$input = [];
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

// POST (multipart)
if ($method === "POST" && strpos($contentType, "multipart/form-data") !== false) {
    $input = $_POST;
}

// PUT (multipart)
elseif ($method === "PUT" && strpos($contentType, "multipart/form-data") !== false) {

    $raw = file_get_contents("php://input");
    $boundary = substr($contentType, strpos($contentType, "boundary=") + 9);
    $blocks = preg_split("/-+$boundary/", $raw);
    array_pop($blocks);

    foreach ($blocks as $block) {
        if (empty(trim($block))) continue;

        // FILE field
        if (strpos($block, 'filename=') !== false) {
            preg_match('/name="([^"]*)"; filename="([^"]*)"/', $block, $m);
            if (!isset($m[1]) || !isset($m[2])) continue;

            $name = $m[1];
            $filename = $m[2];

            preg_match("/Content-Type: (.*)\r\n\r\n/", $block, $typeMatch);
            $mime = trim($typeMatch[1] ?? 'application/octet-stream');

            $fileContent = substr($block, strpos($block, "\r\n\r\n") + 4);
            $fileContent = rtrim($fileContent, "\r\n");

            $tempFile = tempnam(sys_get_temp_dir(), 'php');
            file_put_contents($tempFile, $fileContent);

            $_FILES[$name] = [
                'name' => $filename,
                'type' => $mime,
                'tmp_name' => $tempFile,
                'error' => 0,
                'size' => strlen($fileContent)
            ];
        }

        // NORMAL text fields
        elseif (preg_match('/name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $m)) {
            $input[$m[1]] = trim($m[2]);
        }
    }

// PUT JSON
} elseif ($method === "PUT") {
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) $input = $json;
}

// -------------------------------------------------------------
// TEMPLATE ID (comes from body now, not URL)
// -------------------------------------------------------------
$template_id = intval($input['template_id'] ?? 0);

if ($template_id <= 0) {
    echo json_encode(["status" => false, "message" => "template_id required"]);
    exit;
}

// -------------------------------------------------------------
// Fetch Existing Template
// -------------------------------------------------------------
$stmt = $conn->prepare("SELECT * FROM certificate_templates WHERE id = ? AND institute_id = ? LIMIT 1");
$stmt->bind_param("ii", $template_id, $institute_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    echo json_encode(["status" => false, "message" => "Template not found"]);
    exit;
}

// -------------------------------------------------------------
// BASIC FIELDS
// -------------------------------------------------------------
$template_name = trim($input['template_name'] ?? $existing['template_name']);
$description   = trim($input['description'] ?? $existing['description']);
$is_active     = intval($input['is_active'] ?? $existing['is_active']);
$admin_action  = trim($input['admin_action'] ?? $existing['admin_action']);

// -------------------------------------------------------------
// MEDIA UPLOAD (SAME LOGIC AS BEFORE – ONLY PARSING UPDATED)
// -------------------------------------------------------------
$uploadDir     = __DIR__ . '/../uploads/institute_certificate_templates/';
$relative_path = '/uploads/institute_certificate_templates/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$allowed = ['jpg', 'jpeg', 'png', 'webp'];
$fields  = ['logo', 'seal', 'signature'];

$updated = [];

// Delete helper
function deleteOld($path) {
    if ($path && file_exists(__DIR__ . '/..' . $path)) unlink(__DIR__ . '/..' . $path);
}

foreach ($fields as $f) {

    if (!empty($_FILES[$f]['name'])) {

        $ext = strtolower(pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            echo json_encode(["status" => false, "message" => "Invalid file for $f"]);
            exit;
        }

        // Use original naming system
        $fileName = "certificate_{$institute_id}_{$f}." . $ext;

        // Delete old
        deleteOld($existing[$f]);

        // Save new
        move_uploaded_file($_FILES[$f]['tmp_name'], $uploadDir . $fileName);

        $updated[$f] = $relative_path . $fileName;

    } else {
        $updated[$f] = $existing[$f];
    }
}

// -------------------------------------------------------------
// UPDATE QUERY
// -------------------------------------------------------------
$stmt = $conn->prepare("
    UPDATE certificate_templates 
    SET 
        template_name = ?, 
        logo = ?, 
        seal = ?, 
        signature = ?, 
        description = ?, 
        is_active = ?, 
        admin_action = ?, 
        modified_at = NOW()
    WHERE id = ? AND institute_id = ?
");

$stmt->bind_param(
    "sssssisii",
    $template_name,
    $updated['logo'],
    $updated['seal'],
    $updated['signature'],
    $description,
    $is_active,
    $admin_action,
    $template_id,
    $institute_id
);

$stmt->execute();

// -------------------------------------------------------------
// RESPONSE
// -------------------------------------------------------------
echo json_encode([
    "status"        => true,
    "message"       => "Certificate template updated successfully",
    "template_id"   => $template_id,
    "institute_id"  => $institute_id,
    "template_name" => $template_name,
    "description"   => $description,
    "is_active"     => (bool)$is_active,
    "admin_action"  => $admin_action,
    "logo_url"      => $updated['logo'],
    "seal_url"      => $updated['seal'],
    "signature_url" => $updated['signature']
]);

$conn->close();
?>
