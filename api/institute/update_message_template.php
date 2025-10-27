<?php
// update_message_template.php — Update or toggle message templates
require_once '../cors.php';
require_once '../db.php';

// ✅ Allow only PUT or POST (for Postman flexibility)
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['PUT', 'POST'])) {
    echo json_encode(["status" => false, "message" => "Only PUT or POST method allowed"]);
    exit();
}

// ✅ Authenticate JWT (Admin or Institute)
try {
    $decoded = authenticateJWT(['admin', 'institute']);
} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => "Unauthorized: " . $e->getMessage()]);
    exit();
}

$user_role = strtolower($decoded['role'] ?? 'institute');

// ✅ Parse JSON Input
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["status" => false, "message" => "Invalid JSON input"]);
    exit();
}

// ✅ Validate ID
$template_id = intval($data['id'] ?? 0);
if ($template_id <= 0) {
    echo json_encode(["status" => false, "message" => "Template ID is required"]);
    exit();
}

// ✅ Extract allowed fields
$name              = trim($data['name'] ?? '');
$short_description = trim($data['short_description'] ?? '');
$message_type      = trim($data['message_type'] ?? '');
$delivery_type     = trim($data['delivery_type'] ?? '');
$subject           = trim($data['subject'] ?? '');
$body              = trim($data['body'] ?? '');
$category          = trim($data['category'] ?? '');
$is_active         = isset($data['is_active']) ? intval($data['is_active']) : null;

// ✅ Build update query dynamically
$fields = [];
$params = [];
$types  = "";

if ($name !== '')              { $fields[] = "name=?";              $params[] = $name;              $types .= "s"; }
if ($short_description !== '') { $fields[] = "short_description=?"; $params[] = $short_description; $types .= "s"; }
if ($message_type !== '')      { $fields[] = "message_type=?";      $params[] = $message_type;      $types .= "s"; }
if ($delivery_type !== '')     { $fields[] = "delivery_type=?";     $params[] = $delivery_type;     $types .= "s"; }
if ($subject !== '')           { $fields[] = "subject=?";           $params[] = $subject;           $types .= "s"; }
if ($body !== '')              { $fields[] = "body=?";              $params[] = $body;              $types .= "s"; }
if ($category !== '')          { $fields[] = "category=?";          $params[] = $category;          $types .= "s"; }
if ($is_active !== null)       { $fields[] = "is_active=?";         $params[] = $is_active;         $types .= "i"; }

// ✅ Ensure something to update
if (empty($fields)) {
    echo json_encode(["status" => false, "message" => "No fields provided to update"]);
    exit();
}

// ✅ Add WHERE clause
$params[] = $template_id;
$types .= "i";

$query = "UPDATE message_templates SET " . implode(", ", $fields) . ", updated_at=NOW() WHERE id=?";
$stmt  = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();

// ✅ Respond
if ($stmt->affected_rows > 0) {
    echo json_encode([
        "status" => true,
        "message" => "Template updated successfully",
        "updated_fields" => $fields
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        "status" => false,
        "message" => "No changes made or invalid template ID"
    ], JSON_PRETTY_PRINT);
}
?>
