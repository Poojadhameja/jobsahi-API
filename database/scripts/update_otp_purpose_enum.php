<?php
/**
 * Update otp_requests.purpose enum to include 'phone_login'
 * Run this file once: php database/migrations/update_otp_purpose_enum.php
 */

require_once __DIR__ . '/../../api/db.php';

if (!$conn) {
    die("❌ Database connection failed!\n");
}

echo "🔄 Updating otp_requests.purpose enum...\n\n";

// SQL query to add 'phone_login' to enum
$sql = "ALTER TABLE `otp_requests` 
        MODIFY COLUMN `purpose` ENUM('signup', 'login', 'forgot_password', 'phone_verification', 'phone_verify', 'phone_login') NOT NULL";

if (mysqli_query($conn, $sql)) {
    echo "✅ Success! Enum updated successfully.\n";
    echo "   'phone_login' has been added to otp_requests.purpose enum.\n\n";
    
    // Verify the change
    $check_sql = "SHOW COLUMNS FROM `otp_requests` WHERE Field = 'purpose'";
    $result = mysqli_query($conn, $check_sql);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        echo "📋 Current enum values:\n";
        echo "   " . $row['Type'] . "\n\n";
    }
    
    echo "✅ Database update complete!\n";
} else {
    echo "❌ Error updating enum: " . mysqli_error($conn) . "\n";
    echo "\n💡 If you see 'Duplicate entry' error, the enum might already be updated.\n";
    echo "   You can verify by checking the table structure in phpMyAdmin.\n";
}

mysqli_close($conn);
?>

