<?php
// get_message_templates.php - Fetch all message templates for institute
require_once '../cors.php';

// ✅ Authenticate JWT (Institute / Admin)
$decoded = authenticateJWT(['institute', 'admin']);
$user_role = strtolower($decoded['role'] ?? 'institute');

// ✅ Optional filters (category or type)
$category = $_GET['category'] ?? null;
$message_type = $_GET['message_type'] ?? null;

try {
    $query = "SELECT 
                id, name, short_description, subject, body, message_type, delivery_type, category, created_at
              FROM message_templates 
              WHERE role = ? AND is_active = 1";
    $params = [$user_role];
    $types = "s";

    if ($category) {
        $query .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    if ($message_type) {
        $query .= " AND message_type = ?";
        $params[] = $message_type;
        $types .= "s";
    }

    $query .= " ORDER BY id ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }

    echo json_encode([
        "status" => true,
        "count" => count($templates),
        "data" => $templates
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Error fetching templates: " . $e->getMessage()
    ]);
}
?>
