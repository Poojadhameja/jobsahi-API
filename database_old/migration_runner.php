<?php
require_once __DIR__ . '/../api/db.php';

try {
    echo "✅ Database connected...\n";

    $migrationFiles = glob(__DIR__ . "/migrations/*.php");
    sort($migrationFiles);

    foreach ($migrationFiles as $file) {
        // 🚫 Skip Laravel-style migrations BEFORE including the file
        if (strpos(file_get_contents($file), 'Illuminate\\Database\\Migrations\\Migration') !== false) {
            echo "⏭️  Skipped Laravel migration: {$file}\n";
            continue;
        }

        // ✅ Only include file if it's not a Laravel migration
        require_once $file;

        // Convert file name → class name
        $className = pathinfo($file, PATHINFO_FILENAME);
        $className = preg_replace('/^\d+_/', '', $className);
        $className = str_replace('_', '', ucwords($className, '_'));

        if (class_exists($className)) {
            $migration = new $className();
            $migration->up($pdo);
            echo "✅ Migrated: {$className}\n";
        } else {
            echo "⚠️ Class not found in: {$file}\n";
        }
    }

    echo "\n🎉 All migrations executed successfully.\n";

} catch (PDOException $e) {
    die("❌ PDO Exception: " . $e->getMessage());
} catch (Exception $e) {
    die("❌ Migration failed: " . $e->getMessage());
}
