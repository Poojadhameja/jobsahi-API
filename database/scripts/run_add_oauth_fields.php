<?php
/**
 * Standalone PHP Migration Script
 * Add OAuth fields to users table
 * 
 * NOTE: This script is for EXISTING databases only.
 * For fresh migrations, OAuth fields are included in create_users_table migration.
 * 
 * Run this file directly: php database/scripts/run_add_oauth_fields.php
 */

// Include database connection
require_once __DIR__ . '/../../api/db.php';

echo "🚀 Starting OAuth fields migration...\n\n";

try {
    // Check if users table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (mysqli_num_rows($checkTable) == 0) {
        echo "❌ Users table doesn't exist yet!\n";
        echo "⚠️  Please run users table migration first.\n";
        echo "ℹ️  For fresh migrations, OAuth fields are included in create_users_table migration.\n";
        echo "ℹ️  This script is only needed for existing databases.\n";
        exit(0); // Exit gracefully, not an error
    }
    
    // Check if columns already exist
    $checkColumns = mysqli_query($conn, "SHOW COLUMNS FROM users");
    $existingColumns = [];
    while ($row = mysqli_fetch_assoc($checkColumns)) {
        $existingColumns[] = $row['Field'];
    }
    
    echo "📋 Existing columns: " . implode(', ', $existingColumns) . "\n\n";
    
    // Step 1: Add google_id column
    if (!in_array('google_id', $existingColumns)) {
        echo "➕ Adding google_id column...\n";
        $sql = "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER password";
        if (mysqli_query($conn, $sql)) {
            echo "✅ google_id column added successfully\n";
        } else {
            echo "❌ Error adding google_id: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️  google_id column already exists\n";
    }
    
    // Step 2: Add linkedin_id column
    if (!in_array('linkedin_id', $existingColumns)) {
        echo "➕ Adding linkedin_id column...\n";
        $sql = "ALTER TABLE users ADD COLUMN linkedin_id VARCHAR(255) NULL AFTER google_id";
        if (mysqli_query($conn, $sql)) {
            echo "✅ linkedin_id column added successfully\n";
        } else {
            echo "❌ Error adding linkedin_id: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️  linkedin_id column already exists\n";
    }
    
    // Step 3: Add auth_provider column
    if (!in_array('auth_provider', $existingColumns)) {
        echo "➕ Adding auth_provider column...\n";
        $sql = "ALTER TABLE users ADD COLUMN auth_provider ENUM('email', 'google', 'linkedin') DEFAULT 'email' AFTER linkedin_id";
        if (mysqli_query($conn, $sql)) {
            echo "✅ auth_provider column added successfully\n";
        } else {
            echo "❌ Error adding auth_provider: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️  auth_provider column already exists\n";
    }
    
    // Step 4: Make password nullable
    echo "🔧 Making password column nullable...\n";
    $sql = "ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL";
    if (mysqli_query($conn, $sql)) {
        echo "✅ Password column is now nullable\n";
    } else {
        echo "⚠️  Warning: " . mysqli_error($conn) . "\n";
    }
    
    // Step 5: Add indexes
    echo "\n📊 Adding indexes...\n";
    
    // Check existing indexes
    $checkIndexes = mysqli_query($conn, "SHOW INDEXES FROM users");
    $existingIndexes = [];
    while ($row = mysqli_fetch_assoc($checkIndexes)) {
        $existingIndexes[] = $row['Key_name'];
    }
    
    if (!in_array('idx_google_id', $existingIndexes)) {
        $sql = "ALTER TABLE users ADD INDEX idx_google_id (google_id)";
        if (mysqli_query($conn, $sql)) {
            echo "✅ Index idx_google_id added\n";
        } else {
            echo "⚠️  Index idx_google_id: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️  Index idx_google_id already exists\n";
    }
    
    if (!in_array('idx_linkedin_id', $existingIndexes)) {
        $sql = "ALTER TABLE users ADD INDEX idx_linkedin_id (linkedin_id)";
        if (mysqli_query($conn, $sql)) {
            echo "✅ Index idx_linkedin_id added\n";
        } else {
            echo "⚠️  Index idx_linkedin_id: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️  Index idx_linkedin_id already exists\n";
    }
    
    if (!in_array('idx_auth_provider', $existingIndexes)) {
        $sql = "ALTER TABLE users ADD INDEX idx_auth_provider (auth_provider)";
        if (mysqli_query($conn, $sql)) {
            echo "✅ Index idx_auth_provider added\n";
        } else {
            echo "⚠️  Index idx_auth_provider: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "ℹ️  Index idx_auth_provider already exists\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "🎉 OAuth fields are now available in users table.\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

mysqli_close($conn);
?>

