<?php
$dsn = "mysql:host=localhost;dbname=jobsahi_data;charset=utf8mb4";
$username = "root";   // apna DB user
$password = "";       // apna DB password

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Database connected...\n";

    $migrationFiles = glob(__DIR__ . "/migrations/*.php");
    sort($migrationFiles);

    foreach ($migrationFiles as $file) {
        require_once $file;
        $className = pathinfo($file, PATHINFO_FILENAME);
        $className = preg_replace('/^\d+_/', '', $className); 
        $className = str_replace('_', '', ucwords($className, '_'));

        if (class_exists($className)) {
            $migration = new $className();
            $migration->up($pdo);
            echo "✅ Migrated: {$className}\n";
        } else {
            echo "⚠️ Class not found: {$file}\n";
        }
    }
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
