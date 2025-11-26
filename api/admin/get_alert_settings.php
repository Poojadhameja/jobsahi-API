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
