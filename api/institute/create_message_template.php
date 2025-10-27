<?php
// create_message_template.php - Add a new message template
require_once '../cors.php';

// ✅ Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST method allowed"]);
    exit();
}

// ✅ Authenticate
$decoded = authenticateJWT(['institute', 'admin']);
$user_role = strtolower($decoded['role'] ?? 'institute');

// ✅ Read JSON body
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["status" => false, "message" => "Invalid JSON input"]);
    exit();
}

// ✅ Extract and validate fields
$name              = trim($data['name'] ?? '');
$short_description = trim($data['short_description'] ?? '');
$message_type      = trim($data['message_type'] ?? 'individual');
$subject           = trim($data['subject'] ?? '');
$body              = trim($data['body'] ?? '');
$delivery_type     = trim($data['delivery_type'] ?? 'all');
$category          = trim($data['category'] ?? 'general');

if ($name === '' || $body === '') {
    echo json_encode(["status" => false, "message" => "Name and message body are required"]);
    exit();
}

try {
    $stmt = $conn->prepare("
        INSERT INTO message_templates 
        (name, short_description, message_type, subject, body, delivery_type, role, category)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssssss",
        $name,
        $short_description,
        $message_type,
        $subject,
        $body,
        $delivery_type,
        $user_role,
        $category
    );
    $stmt->execute();

    echo json_encode([
        "status" => true,
        "message" => "Message template created successfully",
        "template_id" => $conn->insert_id
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error creating template: " . $e->getMessage()
    ]);
}
?>
