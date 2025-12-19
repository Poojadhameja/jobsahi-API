<?php
/**
 * Script to create alert_settings table
 * Run this file once to create the table
 * Usage: php create_alert_settings_table.php
 */

require_once '../db.php';

$sql = "CREATE TABLE IF NOT EXISTS `alert_settings` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` varchar(50) NOT NULL COMMENT 'Type of alert: expiry, autoflag, course_deadlines, resume_feedback',
    `settings_json` text NOT NULL COMMENT 'JSON string containing alert settings',
    `created_at` datetime DEFAULT current_timestamp(),
    `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci COMMENT='Stores admin alert automation settings'";

if ($conn->query($sql) === TRUE) {
    echo "✅ Table 'alert_settings' created successfully!\n";
    echo "You can now use the alert settings API.\n";
} else {
    echo "❌ Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>

