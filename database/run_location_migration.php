<?php
/**
 * Manual migration runner for adding latitude and longitude columns to student_profiles table
 * Run this script once to add the required columns to your database
 */

require_once '../api/db.php';

if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}

echo "🔄 Running migration: Add latitude and longitude columns to student_profiles table...\n";

try {
    // Check if columns already exist
    $check_sql = "SHOW COLUMNS FROM student_profiles LIKE 'latitude'";
    $result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($result) > 0) {
        echo "✅ Latitude column already exists. Skipping migration.\n";
    } else {
        // Add latitude column
        $add_latitude_sql = "ALTER TABLE student_profiles ADD COLUMN latitude DECIMAL(10,8) NULL AFTER location";
        if (mysqli_query($conn, $add_latitude_sql)) {
            echo "✅ Added latitude column successfully\n";
        } else {
            throw new Exception("Failed to add latitude column: " . mysqli_error($conn));
        }
    }
    
    // Check if longitude column exists
    $check_sql = "SHOW COLUMNS FROM student_profiles LIKE 'longitude'";
    $result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($result) > 0) {
        echo "✅ Longitude column already exists. Skipping migration.\n";
    } else {
        // Add longitude column
        $add_longitude_sql = "ALTER TABLE student_profiles ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude";
        if (mysqli_query($conn, $add_longitude_sql)) {
            echo "✅ Added longitude column successfully\n";
        } else {
            throw new Exception("Failed to add longitude column: " . mysqli_error($conn));
        }
    }
    
    echo "🎉 Migration completed successfully!\n";
    echo "📍 Your student_profiles table now has latitude and longitude columns.\n";
    echo "🚀 You can now use the /api/update_location.php endpoint.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}

mysqli_close($conn);
?>



