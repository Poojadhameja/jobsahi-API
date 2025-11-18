<?php
// Direct SQL Migration Script
// Run: php database/migrations/run_interview_migration.php

require_once __DIR__ . '/../../api/db.php';

echo "🔄 Running Interview Table Migration...\n\n";

try {
    // Check if columns exist
    $columns_check = $conn->query("SHOW COLUMNS FROM interviews");
    $existing_columns = [];
    while ($col = $columns_check->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }

    // 1. Add platform_name column if it doesn't exist
    if (!in_array('platform_name', $existing_columns)) {
        $conn->query("ALTER TABLE interviews ADD COLUMN platform_name VARCHAR(100) NULL AFTER mode");
        echo "✅ Added platform_name column\n";
    } else {
        echo "⏭️  platform_name column already exists\n";
    }

    // 2. Add interview_link column if it doesn't exist
    if (!in_array('interview_link', $existing_columns)) {
        $conn->query("ALTER TABLE interviews ADD COLUMN interview_link TEXT NULL AFTER platform_name");
        echo "✅ Added interview_link column\n";
    } else {
        echo "⏭️  interview_link column already exists\n";
    }

    // 3. Rename feedback to interview_info if feedback exists and interview_info doesn't
    if (in_array('feedback', $existing_columns) && !in_array('interview_info', $existing_columns)) {
        $conn->query("ALTER TABLE interviews CHANGE feedback interview_info TEXT NULL");
        echo "✅ Renamed feedback column to interview_info\n";
    } elseif (in_array('interview_info', $existing_columns)) {
        echo "⏭️  interview_info column already exists\n";
    } else {
        // If neither exists, create interview_info
        $conn->query("ALTER TABLE interviews ADD COLUMN interview_info TEXT NULL AFTER status");
        echo "✅ Added interview_info column\n";
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}

$conn->close();
?>

