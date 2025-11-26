<?php
require_once '../cors.php';
require_once '../db.php';

// ADMIN ONLY
$decoded = authenticateJWT(['admin']);
if (!$decoded) {
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

// READ JSON
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(["status" => false, "message" => "Invalid JSON"]);
    exit;
}

$type = $data['type'] ?? null;
$settings = $data['settings'] ?? null;

if (!$type || !$settings) {
    echo json_encode(["status" => false, "message" => "Type and settings are required"]);
    exit;
}

$settings_json = json_encode($settings);

try {
    // CHECK IF EXIST
    $check = $conn->prepare("SELECT id FROM alert_settings WHERE type = ? LIMIT 1");
    $check->bind_param("s", $type);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE alert_settings SET settings_json = ? WHERE type = ?");
        $stmt->bind_param("ss", $settings_json, $type);
        $stmt->execute();
    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO alert_settings (type, settings_json) VALUES (?, ?)");
        $stmt->bind_param("ss", $type, $settings_json);
        $stmt->execute();
    }

    echo json_encode([
        "status" => true,
        "message" => ucfirst($type) . " settings updated successfully"
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>
