<?php
require_once '../cors.php';
require_once '../db.php';

// ADMIN ONLY
$decoded = authenticateJWT(['admin']);
if (!$decoded) {
    echo json_encode(["status" => false, "message" => "Unauthorized"]);
    exit;
}

$type = $_GET['type'] ?? null;

if (!$type) {
    echo json_encode(["status" => false, "message" => "Type is required"]);
    exit;
}

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

    // FETCH
    $stmt = $conn->prepare("SELECT settings_json FROM alert_settings WHERE type = ? LIMIT 1");
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {

        // DEFAULTS by type
        $default = [];

        if ($type === 'expiry') {
            $default = [
                "days_before_expiry" => 7,
                "email_alert" => true,
                "sms_alert" => false,
                "whatsapp_alert" => true,
                "inapp_alert" => true,
                "repeat_alert" => false,
                "auto_disable_course" => false
            ];
        }

        if ($type === 'autoflag') {
            $default = [
                "keywords" => [],
                "min_length" => 50,
                "auto_flag_jobs" => false,
                "flag_inactive_users" => false
            ];
        }

        if ($type === 'course_deadlines') {
            $default = [
                "alert_timing" => "7_days_before",
                "email_alert" => true,
                "push_alert" => true,
                "template" => "Reminder: your course {course_name} deadline is approaching."
            ];
        }

        echo json_encode([
            "status" => true,
            "type" => $type,
            "settings" => $default
        ]);
        exit;
    }

    $row = $res->fetch_assoc();
    $settings = json_decode($row['settings_json'], true);

    echo json_encode([
        "status" => true,
        "type" => $type,
        "settings" => $settings
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>
