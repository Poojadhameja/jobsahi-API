<?php
// update_certificate_template.php - Update existing certificate template
require_once '../cors.php';
require_once '../db.php';

// ✅ Allow PUT as well as POST
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['PUT', 'POST'])) {
    echo json_encode(["status" => false, "message" => "Invalid request method"]);
    exit();
}

// ✅ Authenticate JWT (admin or institute)
$decoded = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role'] ?? '');
$user_id   = intval($decoded['user_id'] ?? 0);

// ✅ Identify institute_id
$institute_id = 0;
if ($user_role === 'institute') {
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $institute_id = intval($res->fetch_assoc()['id']);
    } else {
        echo json_encode(["status" => false, "message" => "Institute profile not found"]);
        exit();
    }
} elseif ($user_role === 'admin') {
    $institute_id = intval($_POST['institute_id'] ?? 0);
    if ($institute_id <= 0) {
        echo json_encode(["status" => false, "message" => "Institute ID required for admin"]);
        exit();
    }
}

// ✅ Validate template_id
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
if ($template_id <= 0) {
    echo json_encode(["status" => false, "message" => "Missing or invalid template_id"]);
    exit();
}

// ✅ Fetch existing record
$stmt = $conn->prepare("SELECT * FROM certificate_templates WHERE id = ? AND institute_id = ?");
$stmt->bind_param("ii", $template_id, $institute_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if (!$existing) {
    echo json_encode(["status" => false, "message" => "Template not found or unauthorized"]);
    exit();
}

// ✅ Parse form data (from PUT or POST)
parse_str(file_get_contents("php://input"), $_PUT);
$data = array_merge($_POST, $_PUT);

// ✅ Extract fields safely
$template_name  = trim($data['template_name'] ?? $existing['template_name']);
$header_text    = trim($data['header_text'] ?? $existing['header_text']);
$footer_text    = trim($data['footer_text'] ?? $existing['footer_text']);
$is_active      = trim($data['is_active'] ?? $existing['is_active']);
$admin_action   = trim($data['admin_action'] ?? $existing['admin_action']);

// ✅ File upload + URL update logic
$uploadDir = "../uploads/institute_certificate_templates/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$fileFields = ['logo_url', 'seal_url', 'signature_url', 'background_image_url'];
$updatedUrls = [];

foreach ($fileFields as $field) {
    if (isset($_FILES[$field]['name']) && !empty($_FILES[$field]['name'])) {
        // 🔹 If new file uploaded
        $filename = $field . '_' . time() . '.' . pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
        $destination = $uploadDir . $filename;
        if (move_uploaded_file($_FILES[$field]['tmp_name'], $destination)) {
            $updatedUrls[$field] = "/uploads/institute_certificate_templates/" . $filename;
        } else {
            $updatedUrls[$field] = $existing[$field];
        }
    } elseif (!empty($data[$field])) {
        // 🔹 If URL passed manually in JSON
        $updatedUrls[$field] = trim($data[$field]);
    } else {
        // 🔹 Keep old file if nothing passed
        $updatedUrls[$field] = $existing[$field];
    }
}

// ✅ Update database
$stmt = $conn->prepare("
    UPDATE certificate_templates 
    SET template_name=?, header_text=?, footer_text=?, is_active=?, admin_action=?,
        logo_url=?, seal_url=?, signature_url=?, background_image_url=?,
        modified_at = NOW()
    WHERE id=? AND institute_id=?
");

$stmt->bind_param(
    "sssssssssii", // ✅ 9 strings + 2 integers (fixed count)
    $template_name,
    $header_text,
    $footer_text,
    $is_active,
    $admin_action,
    $updatedUrls['logo_url'],
    $updatedUrls['seal_url'],
    $updatedUrls['signature_url'],
    $updatedUrls['background_image_url'],
    $template_id,
    $institute_id
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => true,
        "message" => "Certificate template updated successfully",
        "template_id" => $template_id,
        "institute_id" => $institute_id,
        "logo_url" => $updatedUrls['logo_url'],
        "seal_url" => $updatedUrls['seal_url'],
        "signature_url" => $updatedUrls['signature_url'],
        "background_image_url" => $updatedUrls['background_image_url']
    ]);
} else {
    echo json_encode([
        "status" => false,
        "message" => "Database update failed: " . $stmt->error
    ]);
}
?>
