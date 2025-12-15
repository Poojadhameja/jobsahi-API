<?php
/**
 * Migration Script: Add profile_image column to student_profiles table
 * 
 * This script adds the profile_image column to store R2 URLs for student profile images
 * Run this script once to update your database schema
 */

require_once __DIR__ . '/../../api/db.php';

try {
    // Check if column already exists
    $check_column_sql = "SELECT COUNT(*) as count 
                         FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'student_profiles' 
                         AND COLUMN_NAME = 'profile_image'";
    
    $result = mysqli_query($conn, $check_column_sql);
    $row = mysqli_fetch_assoc($result);
    
    if ($row['count'] > 0) {
        echo "✅ Column 'profile_image' already exists in student_profiles table.\n";
        exit;
    }
    
    // Add profile_image column
    $add_column_sql = "ALTER TABLE `student_profiles` 
                       ADD COLUMN `profile_image` VARCHAR(500) NULL 
                       AFTER `certificates`";
    
    if (mysqli_query($conn, $add_column_sql)) {
        echo "✅ Successfully added 'profile_image' column to student_profiles table.\n";
        echo "   Column type: VARCHAR(500)\n";
        echo "   Position: After 'certificates' column\n";
    } else {
        echo "❌ Error adding column: " . mysqli_error($conn) . "\n";
        exit(1);
    }
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

