<?php
// Drop FCM Tokens Table
require_once __DIR__ . '/../../api/db.php';

echo "🗑️  Dropping FCM Tokens Table...\n";

$sql = "DROP TABLE IF EXISTS `fcm_tokens`";

if ($conn->query($sql)) {
    echo "✅ Table 'fcm_tokens' dropped successfully!\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

$conn->close();
?>

