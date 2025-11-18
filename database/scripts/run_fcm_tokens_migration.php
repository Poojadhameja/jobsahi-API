<?php
// Migration Script: Create FCM Tokens Table
// Run this file once to create the fcm_tokens table

// Get the correct path to db.php
$db_path = __DIR__ . '/../../api/db.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    // Try alternative path
    $db_path = dirname(__DIR__) . '/../api/db.php';
    require_once $db_path;
}

echo "🔄 Creating FCM Tokens Table...\n\n";

// First, create table without foreign key
$sql = "CREATE TABLE IF NOT EXISTS `fcm_tokens` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `fcm_token` text NOT NULL,
  `device_type` varchar(50) DEFAULT NULL COMMENT 'android, ios, web',
  `device_id` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_device` (`user_id`, `device_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_fcm_token` (`fcm_token`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

try {
    if ($conn->query($sql)) {
        echo "✅ FCM Tokens table created successfully!\n\n";
        
        // Try to add foreign key constraint if users table exists
        echo "🔗 Attempting to add foreign key constraint...\n";
        $fk_sql = "ALTER TABLE `fcm_tokens` 
                   ADD CONSTRAINT `fk_fcm_tokens_user_id` 
                   FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE";
        
        if ($conn->query($fk_sql)) {
            echo "✅ Foreign key constraint added successfully!\n\n";
        } else {
            echo "⚠️  Could not add foreign key constraint: " . $conn->error . "\n";
            echo "ℹ️  Table created without foreign key. This is okay for now.\n\n";
        }
        
        // Check if table exists and show structure
        $check_sql = "SHOW TABLES LIKE 'fcm_tokens'";
        $result = $conn->query($check_sql);
        
        if ($result->num_rows > 0) {
            echo "📋 Table Structure:\n";
            $desc_sql = "DESCRIBE fcm_tokens";
            $desc_result = $conn->query($desc_sql);
            
            echo str_pad("Field", 20) . str_pad("Type", 30) . str_pad("Null", 10) . str_pad("Key", 10) . "Default\n";
            echo str_repeat("-", 100) . "\n";
            
            while ($row = $desc_result->fetch_assoc()) {
                echo str_pad($row['Field'], 20) . 
                     str_pad($row['Type'], 30) . 
                     str_pad($row['Null'], 10) . 
                     str_pad($row['Key'], 10) . 
                     ($row['Default'] ?? 'NULL') . "\n";
            }
            
            echo "\n✅ Migration completed successfully!\n";
        }
    } else {
        echo "❌ Error creating table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    // If table already exists
    if (strpos($e->getMessage(), 'already exists') !== false || strpos($conn->error, 'already exists') !== false) {
        echo "ℹ️  Table 'fcm_tokens' already exists.\n";
        echo "📋 Showing current table structure:\n";
        
        $desc_sql = "DESCRIBE fcm_tokens";
        $desc_result = $conn->query($desc_sql);
        
        echo str_pad("Field", 20) . str_pad("Type", 30) . str_pad("Null", 10) . str_pad("Key", 10) . "Default\n";
        echo str_repeat("-", 100) . "\n";
        
        while ($row = $desc_result->fetch_assoc()) {
            echo str_pad($row['Field'], 20) . 
                 str_pad($row['Type'], 30) . 
                 str_pad($row['Null'], 10) . 
                 str_pad($row['Key'], 10) . 
                 ($row['Default'] ?? 'NULL') . "\n";
        }
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
        if ($conn->error) {
            echo "Database Error: " . $conn->error . "\n";
        }
    }
}

$conn->close();
echo "\n";
?>

