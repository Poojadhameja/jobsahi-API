<?php
// Verify FCM Tokens Table
require_once __DIR__ . '/../../api/db.php';

echo "🔍 Checking FCM Tokens Table...\n\n";

// Check if table exists
$check_sql = "SHOW TABLES LIKE 'fcm_tokens'";
$result = $conn->query($check_sql);

if ($result->num_rows > 0) {
    echo "✅ Table 'fcm_tokens' exists!\n\n";
    
    // Show table structure
    echo "📋 Table Structure:\n";
    echo str_repeat("=", 100) . "\n";
    echo str_pad("Field", 20) . str_pad("Type", 30) . str_pad("Null", 10) . str_pad("Key", 10) . "Default\n";
    echo str_repeat("-", 100) . "\n";
    
    $desc_sql = "DESCRIBE fcm_tokens";
    $desc_result = $conn->query($desc_sql);
    
    while ($row = $desc_result->fetch_assoc()) {
        echo str_pad($row['Field'], 20) . 
             str_pad($row['Type'], 30) . 
             str_pad($row['Null'], 10) . 
             str_pad($row['Key'], 10) . 
             ($row['Default'] ?? 'NULL') . "\n";
    }
    
    echo "\n";
    
    // Count records
    $count_sql = "SELECT COUNT(*) as total FROM fcm_tokens";
    $count_result = $conn->query($count_sql);
    $count = $count_result->fetch_assoc()['total'];
    echo "📊 Total records: $count\n\n";
    
    echo "✅ Table is ready to use!\n";
    echo "ℹ️  Note: Foreign key constraint was not added, but table is functional.\n";
    
} else {
    echo "❌ Table 'fcm_tokens' does not exist!\n";
    echo "Run: php database/migrations/run_fcm_tokens_migration.php\n";
}

$conn->close();
?>

