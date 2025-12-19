<?php
/**
 * Migration: Create alert_settings table
 * Date: 2025-12-19
 * Description: Creates the alert_settings table for storing admin alert automation settings
 */

function up() {
    global $conn;
    
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
        echo "✅ Table 'alert_settings' created successfully\n";
        return true;
    } else {
        echo "❌ Error creating table 'alert_settings': " . $conn->error . "\n";
        return false;
    }
}

function down() {
    global $conn;
    
    $sql = "DROP TABLE IF EXISTS `alert_settings`";
    
    if ($conn->query($sql) === TRUE) {
        echo "✅ Table 'alert_settings' dropped successfully\n";
        return true;
    } else {
        echo "❌ Error dropping table 'alert_settings': " . $conn->error . "\n";
        return false;
    }
}

