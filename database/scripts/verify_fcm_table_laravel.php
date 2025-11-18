<?php
// Verify FCM Tokens Table (Laravel way)
require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔍 Checking FCM Tokens Table...\n\n";

if (Schema::hasTable('fcm_tokens')) {
    echo "✅ Table 'fcm_tokens' exists!\n\n";
    
    // Show table structure
    echo "📋 Table Structure:\n";
    echo str_repeat("=", 100) . "\n";
    
    $columns = DB::select("DESCRIBE fcm_tokens");
    echo str_pad("Field", 20) . str_pad("Type", 30) . str_pad("Null", 10) . str_pad("Key", 10) . "Default\n";
    echo str_repeat("-", 100) . "\n";
    
    foreach ($columns as $col) {
        $col = (array)$col;
        echo str_pad($col['Field'], 20) . 
             str_pad($col['Type'], 30) . 
             str_pad($col['Null'], 10) . 
             str_pad($col['Key'], 10) . 
             ($col['Default'] ?? 'NULL') . "\n";
    }
    
    // Check foreign keys
    echo "\n🔗 Foreign Keys:\n";
    $foreignKeys = DB::select("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'fcm_tokens'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if (count($foreignKeys) > 0) {
        foreach ($foreignKeys as $fk) {
            $fk = (array)$fk;
            echo "  ✅ {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "  ⚠️  No foreign keys found\n";
    }
    
    // Count records
    $count = DB::table('fcm_tokens')->count();
    echo "\n📊 Total records: $count\n\n";
    
    echo "✅ Table is ready to use!\n";
} else {
    echo "❌ Table 'fcm_tokens' does not exist!\n";
}

