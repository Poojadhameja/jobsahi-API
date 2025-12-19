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
    // CHECK IF TABLE EXISTS, CREATE IF NOT
    $table_check = $conn->query("SHOW TABLES LIKE 'alert_settings'");
    if ($table_check->num_rows == 0) {
        // CREATE TABLE IF NOT EXISTS
        $create_table_sql = "CREATE TABLE IF NOT EXISTS `alert_settings` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` varchar(50) NOT NULL COMMENT 'Type of alert: expiry, autoflag, course_deadlines, resume_feedback',
            `settings_json` text NOT NULL COMMENT 'JSON string containing alert settings',
            `created_at` datetime DEFAULT current_timestamp(),
            `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci COMMENT='Stores admin alert automation settings'";
        
        if (!$conn->query($create_table_sql)) {
            throw new Exception("Failed to create alert_settings table: " . $conn->error);
        }
    }

    // CHECK IF EXIST
    $check = $conn->prepare("SELECT id FROM alert_settings WHERE type = ? LIMIT 1");
    $check->bind_param("s", $type);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE alert_settings SET settings_json = ?, updated_at = NOW() WHERE type = ?");
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
