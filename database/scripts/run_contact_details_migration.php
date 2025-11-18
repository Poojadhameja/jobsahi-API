<?php
// Run migration to add contact_email and contact_phone to student_profiles table

require_once __DIR__ . '/../../api/db.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

echo "🔄 Running migration: Add contact_email and contact_phone to student_profiles table...\n\n";

// Include and run the migration
require_once __DIR__ . '/2025_12_19_000001_add_contact_details_to_student_profiles_table.php';

$migration = new class {
    public function up(): void {
        global $conn;
        
        // ✅ Add contact_email and contact_phone columns to student_profiles table
        $sql = "ALTER TABLE `student_profiles` 
                ADD COLUMN `contact_email` VARCHAR(255) NULL AFTER `location`,
                ADD COLUMN `contact_phone` VARCHAR(20) NULL AFTER `contact_email`";
        
        if (mysqli_query($conn, $sql)) {
            echo "✅ Successfully added contact_email and contact_phone columns to student_profiles table\n";
        } else {
            $error = mysqli_error($conn);
            // Check if columns already exist
            if (strpos($error, 'Duplicate column name') !== false) {
                echo "⚠️  Columns contact_email and/or contact_phone already exist in student_profiles table\n";
            } else {
                echo "❌ Error adding columns: " . $error . "\n";
                die("Migration failed: " . $error);
            }
        }
    }
};

try {
    // Check if table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'student_profiles'");
    if (mysqli_num_rows($table_check) === 0) {
        echo "❌ Table 'student_profiles' does not exist!\n";
        echo "Please create the student_profiles table first.\n";
        exit(1);
    }
    
    // Check if columns already exist
    $columns_check = mysqli_query($conn, "SHOW COLUMNS FROM student_profiles");
    $existing_columns = [];
    while ($col = mysqli_fetch_assoc($columns_check)) {
        $existing_columns[] = $col['Field'];
    }
    
    echo "📋 Checking existing columns...\n";
    echo "   Found columns: " . implode(", ", $existing_columns) . "\n\n";
    
    // Check and add contact_email
    if (in_array('contact_email', $existing_columns)) {
        echo "⏭️  contact_email column already exists\n";
    } else {
        echo "➕ Adding contact_email column...\n";
        $sql_email = "ALTER TABLE `student_profiles` 
                      ADD COLUMN `contact_email` VARCHAR(255) NULL AFTER `location`";
        if (mysqli_query($conn, $sql_email)) {
            echo "✅ Successfully added contact_email column\n";
        } else {
            echo "❌ Error adding contact_email: " . mysqli_error($conn) . "\n";
        }
    }
    
    // Check and add contact_phone
    if (in_array('contact_phone', $existing_columns)) {
        echo "⏭️  contact_phone column already exists\n";
    } else {
        echo "➕ Adding contact_phone column...\n";
        $sql_phone = "ALTER TABLE `student_profiles` 
                      ADD COLUMN `contact_phone` VARCHAR(20) NULL AFTER `contact_email`";
        if (mysqli_query($conn, $sql_phone)) {
            echo "✅ Successfully added contact_phone column\n";
        } else {
            echo "❌ Error adding contact_phone: " . mysqli_error($conn) . "\n";
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

mysqli_close($conn);

