<?php
// update_certificate_template.php - Update existing certificate template (ONLY PUT)
require_once '../cors.php';
require_once '../db.php';

// -------------------------------------------------------------
// ALLOW ONLY PUT
// -------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'PUT') {
    echo json_encode(["status" => false, "message" => "Only PUT allowed"]);
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
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($json['institute_id'])) {
        echo json_encode(["status" => false, "message" => "Admin must send institute_id in PUT body"]);
        exit;
    }

    $institute_id = intval($json['institute_id']);
}

// -------------------------------------------------------------
// PARSE INPUT (MULTIPART + JSON SUPPORT)
// -------------------------------------------------------------
$input = [];
$contentType = $_SERVER["CONTENT_TYPE"] ?? "";

// multipart/form-data PUT
if (strpos($contentType, "multipart/form-data") !== false) {

    $raw = file_get_contents("php://input");
    $boundary = substr($contentType, strpos($contentType, "boundary=") + 9);
    $blocks = preg_split("/-+$boundary/", $raw);
    array_pop($blocks);

    foreach ($blocks as $block) {
        if (empty(trim($block))) continue;

        // FILE PROCESSING
        if (strpos($block, 'filename=') !== false) {

            preg_match('/name="([^"]*)"; filename="([^"]*)"/', $block, $m);
            if (!isset($m[1]) || !isset($m[2])) continue;

            $fieldName = $m[1];
            $filename  = $m[2];

            preg_match("/Content-Type: (.*)\r\n\r\n/", $block, $typeMatch);
            $mime = trim($typeMatch[1] ?? 'application/octet-stream');

            $fileContent = substr($block, strpos($block, "\r\n\r\n") + 4);
            $fileContent = rtrim($fileContent, "\r\n");

            $temp = tempnam(sys_get_temp_dir(), "put_");
            file_put_contents($temp, $fileContent);

            $_FILES[$fieldName] = [
                "name"     => $filename,
                "type"     => $mime,
                "tmp_name" => $temp,
                "error"    => 0,
                "size"     => strlen($fileContent)
            ];
        }
        // TEXT FIELD
        elseif (preg_match('/name="([^"]*)"\r\n\r\n(.*)\r\n/', $block, $m)) {
            $input[$m[1]] = trim($m[2]);
        }
    }

} else {
    // Handle JSON PUT
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $input = $json;
    }
}

// -------------------------------------------------------------
// REQUIRE TEMPLATE ID
// -------------------------------------------------------------
$template_id = intval($input['template_id'] ?? 0);
if ($template_id <= 0) {
    echo json_encode(["status" => false, "message" => "template_id required"]);
    exit;
}

// -------------------------------------------------------------
// FETCH EXISTING TEMPLATE
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
// MEDIA UPLOAD — SAME AS CREATE API
// -------------------------------------------------------------
$upload_dir    = __DIR__ . '/../uploads/institute_certificate_templates/';
$relative_path = '/uploads/institute_certificate_templates/';

if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$file_fields = ['logo', 'seal', 'signature'];

function removeOld($path) {
    if ($path && file_exists(__DIR__ . '/..' . $path)) {
        unlink(__DIR__ . '/..' . $path);
    }
}

$updated = [];

foreach ($file_fields as $field) {

    if (!empty($_FILES[$field]['name'])) {

        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_extensions)) {
            echo json_encode(["status" => false, "message" => "Invalid file for $field"]);
            exit;
        }

        // 💥 SAME NAMING FORMAT AS CREATE API
        $filename = 'certificate_' . $user_id . '_' . $field . '.' . $ext;
        $destination = $upload_dir . $filename;

        // Delete old file
        removeOld($existing[$field]);

        // PUT uploads do not support move_uploaded_file()
        if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $destination)) {

            // fallback for PUT
            if (!@rename($_FILES[$field]['tmp_name'], $destination)) {
                echo json_encode(["status" => false, "message" => "Failed uploading $field"]);
                exit;
            }
        }

        $updated[$field] = $relative_path . $filename;

    } else {
        $updated[$field] = $existing[$field];
    }
}

// -------------------------------------------------------------
// UPDATE DB
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
    "logo"          => $updated['logo'],
    "seal"          => $updated['seal'],
    "signature"     => $updated['signature']
]);

$conn->close();
?>
