<?php
// Fix for Campus Applications Table Schema
// Run this script once to allow NULL values for preferences 2-6

// Adjust the path to db.php as needed
// Assuming this file is in campus_drive/ folder and db.php is in the parent directory (api/)
$db_path = '../db.php'; 

if (!file_exists($db_path)) {
    // Try one level up if in candidate folder
    $db_path = '../../db.php';
}

if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("Error: Could not find db.php. Please make sure database connection file exists.");
}

echo "Attempting to fix database schema...<br>";

// 1. Alter table to allow NULL for preferences 2-6
$sql = "ALTER TABLE `campus_applications` 
    MODIFY `pref2_company_id` INT(11) NULL,
    MODIFY `pref3_company_id` INT(11) NULL,
    MODIFY `pref4_company_id` INT(11) NULL,
    MODIFY `pref5_company_id` INT(11) NULL,
    MODIFY `pref6_company_id` INT(11) NULL";

if ($conn->query($sql) === TRUE) {
    echo "✅ Success: Table `campus_applications` altered to allow NULL preferences.<br>";
} else {
    echo "❌ Error altering table: " . $conn->error . "<br>";
}

// 2. Also ensure foreign keys allow null (usually they do if column does)
// If there are issues, we might need to drop and recreate FKs, but usually MODIFY handles it.

echo "Done.";
?>