<?php
// create_certificate_template.php - Create new certificate template (Admin / Institute)
require_once '../cors.php';
require_once '../db.php';
require_once '../helpers/r2_uploader.php'; // ✅ R2 Uploader

// --------------------------------------------
// 🔐 Authenticate JWT
// --------------------------------------------
$decoded   = authenticateJWT(['admin', 'institute']);
$user_role = strtolower($decoded['role'] ?? '');
$user_id   = intval($decoded['user_id'] ?? 0);

// --------------------------------------------
// 🎯 Detect institute_id
// --------------------------------------------
$institute_id = 0;

if ($user_role === 'institute') {

    $stmt = $conn->prepare("
        SELECT id 
        FROM institute_profiles 
        WHERE user_id = ? AND deleted_at IS NULL 
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $r = $stmt->get_result();

    if ($r->num_rows == 0) {
        echo json_encode(["status" => false, "message" => "Institute profile not found"]);
        exit;
    }

    $institute_id = intval($r->fetch_assoc()['id']);
    $stmt->close();

} elseif ($user_role === 'admin') {
    // Admin impersonation: Get institute_id from request parameters
    // Check multiple possible parameter names
    $provided_id = 0;
    if (isset($_POST['institute_id']) && !empty($_POST['institute_id'])) {
        $provided_id = intval($_POST['institute_id']);
    } elseif (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $provided_id = intval($_POST['user_id']);
    } elseif (isset($_POST['uid']) && !empty($_POST['uid'])) {
        $provided_id = intval($_POST['uid']);
    } elseif (isset($_POST['instituteId']) && !empty($_POST['instituteId'])) {
        $provided_id = intval($_POST['instituteId']);
    }

    if ($provided_id <= 0) {
        echo json_encode(["status" => false, "message" => "Institute ID required"]);
        exit;
    }

    // Try to find institute_id - first check if it's already an institute_profiles.id
    $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("i", $provided_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $inst = $res->fetch_assoc();
    $stmt->close();

    if ($inst && isset($inst['id'])) {
        // Found by institute_profiles.id
        $institute_id = intval($inst['id']);
        
        // Double-check that this institute_id exists and is valid
        if ($institute_id <= 0) {
            echo json_encode(["status" => false, "message" => "Invalid institute ID"]);
            exit;
        }
    } else {
        // Not found by id, try to find by user_id (convert user_id to institute_id)
        $stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param("i", $provided_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $inst = $res->fetch_assoc();
        $stmt->close();

        if ($inst && isset($inst['id'])) {
            // Found by user_id
            $institute_id = intval($inst['id']);
            
            // Double-check that this institute_id exists and is valid
            if ($institute_id <= 0) {
                echo json_encode(["status" => false, "message" => "Invalid institute ID"]);
                exit;
            }
        } else {
            // Log for debugging
            error_log("Certificate Template Creation Failed: Provided ID $provided_id does not match any institute_profiles.id or user_id");
            echo json_encode([
                "status" => false, 
                "message" => "Institute not found. Please ensure the institute profile exists.",
                "debug" => "Provided ID: $provided_id"
            ]);
            exit;
        }
    }
    
    // Final validation: Verify institute_id exists before proceeding
    $verify_stmt = $conn->prepare("SELECT id FROM institute_profiles WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $verify_stmt->bind_param("i", $institute_id);
    $verify_stmt->execute();
    $verify_res = $verify_stmt->get_result();
    $verify_inst = $verify_res->fetch_assoc();
    $verify_stmt->close();
    
    if (!$verify_inst || !isset($verify_inst['id'])) {
        error_log("Certificate Template Creation Failed: Final verification failed for institute_id: $institute_id");
        echo json_encode([
            "status" => false, 
            "message" => "Institute validation failed. Please contact support.",
            "debug" => "Institute ID: $institute_id"
        ]);
        exit;
    }
}

// --------------------------------------------
// ❌ Duplicate template name check
// --------------------------------------------
$template_name = trim($_POST['template_name'] ?? '');

$chk = $conn->prepare("
    SELECT id 
    FROM certificate_templates 
    WHERE template_name = ? 
      AND institute_id = ? 
      AND deleted_at IS NULL
");
$chk->bind_param("si", $template_name, $institute_id);
$chk->execute();
$res = $chk->get_result();

if ($res->num_rows > 0) {
    echo json_encode([
        "status" => false,
        "message" => "A certificate template with this name already exists."
    ]);
    exit;
}
$chk->close();

// --------------------------------------------
// 🟦 POST Only
// --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST allowed"]);
    exit;
}

// --------------------------------------------
// 📂 Upload folder setup
// --------------------------------------------
$upload_dir    = __DIR__ . '/../uploads/institute_certificate_templates/';
$relative_path = '/uploads/institute_certificate_templates/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

// --------------------------------------------
// 📤 File Upload Function (R2)
// --------------------------------------------
function uploadFileToR2($key, $allowed_extensions, $user_id) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;

    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) return null;

    // ✅ Upload to R2
    $r2Path = "certificate_templates/certificate_{$user_id}_{$key}." . $ext;
    $uploadResult = R2Uploader::uploadFile($_FILES[$key]['tmp_name'], $r2Path);

    if (!$uploadResult['success']) {
        return null;
    }

    // ✅ Return R2 URL
    return $uploadResult['url'];
}

// --------------------------------------------
// 🔎 Fetch last template of this institute 
// (for inheriting logo/seal/signature)
// --------------------------------------------
$last_logo = null;
$last_seal = null;
$last_signature = null;

$last = $conn->prepare("
    SELECT logo, seal, signature 
    FROM certificate_templates 
    WHERE institute_id = ? 
      AND deleted_at IS NULL
    ORDER BY id DESC 
    LIMIT 1
");
$last->bind_param("i", $institute_id);
$last->execute();
$lastRes = $last->get_result();

if ($lastRes->num_rows > 0) {
    $row = $lastRes->fetch_assoc();
    $last_logo = $row['logo'];
    $last_seal = $row['seal'];
    $last_signature = $row['signature'];
}
$last->close();

// --------------------------------------------
// 📝 Input Data
// --------------------------------------------
$description  = trim($_POST['description'] ?? '');
$is_active    = intval($_POST['is_active'] ?? 1);

// --------------------------------------------
// 📤 Upload new files to R2 or inherit old ones
// --------------------------------------------
$logo_path      = uploadFileToR2('logo', $allowed_extensions, $user_id);
$seal_path      = uploadFileToR2('seal', $allowed_extensions, $user_id);
$signature_path = uploadFileToR2('signature', $allowed_extensions, $user_id);

// If no new upload → reuse old template media
if (!$logo_path)      $logo_path      = $last_logo;
if (!$seal_path)      $seal_path      = $last_seal;
if (!$signature_path) $signature_path = $last_signature;

// --------------------------------------------
// 🗄 Insert New Template (admin_action REMOVED)
// --------------------------------------------
$stmt = $conn->prepare("
    INSERT INTO certificate_templates
    (institute_id, template_name, logo, seal, signature, description, is_active, created_at, modified_at, deleted_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NULL)
");
$stmt->bind_param(
    "isssssi",
    $institute_id,
    $template_name,
    $logo_path,
    $seal_path,
    $signature_path,
    $description,
    $is_active
);

if ($stmt->execute()) {

    echo json_encode([
        "status"        => true,
        "message"       => "Certificate template created successfully",
        "template_id"   => $stmt->insert_id,   // ⭐ TEMPLATE ID ADDED
        "institute_id"  => $institute_id,
        "template_name" => $template_name,
        "description"   => $description,
        "is_active"     => (bool)$is_active,

        // Returned media
        "logo"          => $logo_path,
        "seal"          => $seal_path,
        "signature"     => $signature_path
    ]);

} else {
    echo json_encode([
        "status" => false,
        "message" => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
