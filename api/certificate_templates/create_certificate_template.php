<?php
// create_certificate_template.php - Create new certificate template (Admin / Institute)
require_once '../cors.php';
require_once '../db.php';

// ✅ Authenticate JWT
$decoded = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role'] ?? '');
$user_id   = intval($decoded['user_id'] ?? 0);

// ✅ Fetch institute_id
$institute_id = 0;
if ($user_role === 'institute') {
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $institute_id = intval($res->fetch_assoc()['id']);
    } else {
        echo json_encode(["status" => false, "message" => "Institute profile not found for user_id $user_id"]);
        exit();
    }
} elseif ($user_role === 'admin') {
    $institute_id = intval($_POST['institute_id'] ?? 0);
}

// ✅ Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST allowed"]);
    exit();
}

// ✅ Define upload folder
$upload_dir = __DIR__ . '/../uploads/institute_certificate_templates/';
$relative_path = '/uploads/institute_certificate_templates/';

// Ensure directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ✅ Allowed extensions
$allowed_extensions = ['jpg', 'jpeg', 'png'];

// ✅ Helper: Upload File
function uploadFile($key, $upload_dir, $relative_path, $allowed_extensions)
{
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $_FILES[$key]['tmp_name'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_extensions)) {
        echo json_encode([
            "status" => false,
            "message" => "Invalid file type for $key. Only JPG, JPEG, PNG allowed."
        ]);
        exit();
    }

    // Unique filename
    $filename = $key . '_' . time() . '.' . $ext;
    $destination = rtrim($upload_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($tmp, $destination)) {
        return $relative_path . $filename;
    }

    return null;
}

// ✅ Collect form data
$template_name  = trim($_POST['template_name'] ?? 'Default Certificate');
$header_text    = trim($_POST['header_text'] ?? 'Certificate of Completion');
$footer_text    = trim($_POST['footer_text'] ?? 'Powered by JobSahi');
$is_active      = intval($_POST['is_active'] ?? 1);
$admin_action   = trim($_POST['admin_action'] ?? 'approved');
$created_at     = date('Y-m-d H:i:s');
$modified_at    = date('Y-m-d H:i:s');

// ✅ Upload using your Postman field names
$logo_url             = uploadFile('logo_url', $upload_dir, $relative_path, $allowed_extensions);
$seal_url             = uploadFile('seal_url', $upload_dir, $relative_path, $allowed_extensions);
$signature_url        = uploadFile('signature_url', $upload_dir, $relative_path, $allowed_extensions);
$background_image_url = uploadFile('background_image_url', $upload_dir, $relative_path, $allowed_extensions);

try {
    // ✅ Insert into DB
    $stmt = $conn->prepare("
        INSERT INTO certificate_templates 
        (institute_id, template_name, logo_url, seal_url, signature_url,
         header_text, footer_text, background_image_url, is_active, created_at, modified_at, deleted_at, admin_action)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)
    ");

    $stmt->bind_param(
        "isssssssssss",
        $institute_id,
        $template_name,
        $logo_url,
        $seal_url,
        $signature_url,
        $header_text,
        $footer_text,
        $background_image_url,
        $is_active,
        $created_at,
        $modified_at,
        $admin_action
    );

    if ($stmt->execute()) {
        echo json_encode([
            "status" => true,
            "message" => "Certificate template created successfully",
            "template_id" => $stmt->insert_id,
            "institute_id" => $institute_id,
            "logo_url" => $logo_url,
            "seal_url" => $seal_url,
            "signature_url" => $signature_url,
            "background_image_url" => $background_image_url
        ]);
    } else {
        echo json_encode([
            "status" => false,
            "message" => "Database insert failed",
            "error" => $stmt->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>
