<?php
// update_certificate_template.php - Update existing certificate template (ONLY PUT)
require_once '../cors.php';
require_once '../db.php';
require_once '../helpers/r2_uploader.php'; // ✅ R2 Uploader

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

// -------------------------------------------------------------
// MEDIA UPLOAD TO R2
// -------------------------------------------------------------
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
$file_fields = ['logo', 'seal', 'signature'];

$updated = [];

foreach ($file_fields as $field) {

    if (!empty($_FILES[$field]['name'])) {

        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_extensions)) {
            echo json_encode(["status" => false, "message" => "Invalid file for $field"]);
            exit;
        }

        // ✅ Delete old file from R2 if exists
        if (!empty($existing[$field])) {
            $old_path = $existing[$field];
            
            // Check if old file is R2 URL
            if (strpos($old_path, 'r2.dev') !== false || strpos($old_path, 'r2.cloudflarestorage.com') !== false) {
                // Extract R2 path from URL
                $parsedUrl = parse_url($old_path);
                $r2Path = ltrim($parsedUrl['path'], '/');
                
                // Remove bucket name if present
                if (strpos($r2Path, 'jobsahi-media/') === 0) {
                    $r2Path = str_replace('jobsahi-media/', '', $r2Path);
                }
                
                // Delete from R2
                R2Uploader::deleteFile($r2Path);
            } else {
                // Old local file (backward compatibility)
                if ($old_path && file_exists(__DIR__ . '/..' . $old_path)) {
                    unlink(__DIR__ . '/..' . $old_path);
                }
            }
        }

        // ✅ Upload to R2
        $r2Path = "certificate_templates/certificate_{$user_id}_{$field}." . $ext;
        $uploadResult = R2Uploader::uploadFile($_FILES[$field]['tmp_name'], $r2Path);

        if (!$uploadResult['success']) {
            echo json_encode([
                "status" => false,
                "message" => "Failed uploading $field: " . $uploadResult['message']
            ]);
            exit;
        }

        // ✅ Use R2 URL
        $updated[$field] = $uploadResult['url'];

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
        modified_at = NOW()
    WHERE id = ? AND institute_id = ?
");

$stmt->bind_param(
    "sssssisi",
    $template_name,
    $updated['logo'],
    $updated['seal'],
    $updated['signature'],
    $description,
    $is_active,
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
    "logo"          => $updated['logo'],
    "seal"          => $updated['seal'],
    "signature"     => $updated['signature']
]);

$conn->close();
?>
