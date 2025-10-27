<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "⚙️  Starting migration script...\n";

// ✅ vendor autoload (1 level up)
$autoload = dirname(__DIR__, 1) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "❌ vendor/autoload.php not found at: $autoload\n";
    exit(1);
}
require $autoload;
echo "✅ Autoload loaded.\n";

// ✅ Load .env (from root)
$envPath = dirname(__DIR__, 1) . '/.env';
if (file_exists($envPath)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1))->load();
    echo "✅ .env loaded.\n";
} else {
    echo "⚠️  .env not found, using default DB creds.\n";
}

// ✅ DB creds
$DB_HOST = $_ENV['DB_HOST'] ?? '127.0.0.1';
$DB_PORT = $_ENV['DB_PORT'] ?? '3306';
$DB_NAME = $_ENV['DB_DATABASE'] ?? 'jobsahi_database_shared';
$DB_USER = $_ENV['DB_USERNAME'] ?? 'root';
$DB_PASS = $_ENV['DB_PASSWORD'] ?? '';

echo "📦 DB: $DB_NAME @ $DB_HOST:$DB_PORT\n";

// ✅ SQL dump
$SQL_FILE = __DIR__ . '/sql/jobsahi_database_shared_new.sql';
if (!file_exists($SQL_FILE)) {
    echo "❌ SQL file not found: $SQL_FILE\n";
    exit(1);
}
echo "✅ SQL file found: $SQL_FILE\n";

// ✅ connect to MySQL
try {
    $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ MySQL connected.\n";

    // drop + recreate DB
    $pdo->exec("DROP DATABASE IF EXISTS `$DB_NAME`");
    $pdo->exec("CREATE DATABASE `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `$DB_NAME`");
    echo "✅ Database recreated.\n";

    // run SQL
    // run SQL safely with foreign key checks disabled
    $sql = file_get_contents($SQL_FILE);

    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
        $pdo->exec($sql);
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
        echo "🎉 Migration completed successfully!\n";
    } catch (Throwable $err) {
        echo "❌ SQL Error: " . $err->getMessage() . "\n";
    }
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
