<?php
// create_certificate_template.php - Create new certificate template (Admin / Institute)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT
$decoded   = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role'] ?? '');
$user_id   = intval($decoded['user_id'] ?? 0);

// ✅ Fetch institute_id (for institute user)
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
    $institute_id = intval($_POST['institute_id'] ?? 0);
}

// ❌ Prevent duplicate template names per institute
$template_name = trim($_POST['template_name'] ?? '');

$check = $conn->prepare("
    SELECT id FROM certificate_templates 
    WHERE template_name = ? AND institute_id = ? AND deleted_at IS NULL
");
$check->bind_param("si", $template_name, $institute_id);
$check->execute();
$dup = $check->get_result();
if ($dup && $dup->num_rows > 0) {
    echo json_encode([
        "status" => false,
        "message" => "A certificate template with this name already exists."
    ]);
    exit;
}
$check->close();

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST allowed"]);
    exit;
}

// Upload folders
$upload_dir    = __DIR__ . '/../uploads/institute_certificate_templates/';
$relative_path = '/uploads/institute_certificate_templates/';

if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// Allowed extensions
$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

/* =======================================================
    🔥 uploadFile() — UNIQUE FILENAME FORMAT:
    certificate_<user_id>_<fieldname>.<ext>
========================================================= */
function uploadFile($key, $upload_dir, $relative_path, $allowed_extensions, $user_id) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;

    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) return null;

    // UNIQUE FILE NAME FORMAT
    $filename = 'certificate_' . $user_id . '_' . $key . '.' . $ext;

    $destination = $upload_dir . $filename;

    // If file already exists → delete before saving
    if (file_exists($destination)) {
        unlink($destination);
    }

    move_uploaded_file($_FILES[$key]['tmp_name'], $destination);

    return $relative_path . $filename;
}

// Collect data
$description  = trim($_POST['description'] ?? '');
$is_active    = intval($_POST['is_active'] ?? 1);
$admin_action = trim($_POST['admin_action'] ?? 'approved');
$created_at   = date('Y-m-d H:i:s');

// Upload files
$logo_path      = uploadFile('logo', $upload_dir, $relative_path, $allowed_extensions, $user_id);
$seal_path      = uploadFile('seal', $upload_dir, $relative_path, $allowed_extensions, $user_id);
$signature_path = uploadFile('signature', $upload_dir, $relative_path, $allowed_extensions, $user_id);

// Insert DB
$stmt = $conn->prepare("
    INSERT INTO certificate_templates
    (institute_id, template_name, logo, seal, signature, description, is_active, created_at, modified_at, deleted_at, admin_action)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NULL, ?)
");
$stmt->bind_param(
    "isssssis",
    $institute_id,
    $template_name,
    $logo_path,
    $seal_path,
    $signature_path,
    $description,
    $is_active,
    $admin_action
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Certificate template created successfully",
          "institute_id"  => $institute_id,
          "template_name" => $template_name,
          "description"   => $description,
          "is_active"     => (bool)$is_active,
          "admin_action"  => $admin_action,
          // consistent with UPDATE API
          "logo"          => $logo_path,
          "seal"          => $seal_path,
          "signature"     => $signature_path
    ]);
} else {
    echo json_encode(["status" => false, "message" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
