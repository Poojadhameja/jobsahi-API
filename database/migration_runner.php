<?php
<<<<<<< HEAD
=======
/**
 * JobSahi Migration Runner (Scratch Build)
 * Usage:
 *   php database/migration_runner.php status
 *   php database/migration_runner.php up
 */
require_once __DIR__ . '/../api/db.php'; // ← uses same creds as APIs
>>>>>>> origin/pooja

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
$DB_NAME = $_ENV['DB_DATABASE'] ?? 'database';
$DB_USER = $_ENV['DB_USERNAME'] ?? 'root';
$DB_PASS = $_ENV['DB_PASSWORD'] ?? '';

echo "📦 DB: $DB_NAME @ $DB_HOST:$DB_PORT\n";

// ✅ SQL dump
$SQL_FILE = __DIR__ . '/sql/database.sql';
if (!file_exists($SQL_FILE)) {
    echo "❌ SQL file not found: $SQL_FILE\n";
    exit(1);
}
echo "✅ SQL file found: $SQL_FILE\n";

// ✅ connect to MySQL
try {
<<<<<<< HEAD
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
=======
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  die("❌ DB connect failed: ".$e->getMessage()."\n");
>>>>>>> origin/pooja
}

$pdo->exec("CREATE TABLE IF NOT EXISTS _migrations(
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL UNIQUE,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$dir = __DIR__ . '/migrations';
if (!is_dir($dir)) { die("Missing migrations dir\n"); }

$files = glob($dir.'/*.sql');
natsort($files);
$applied = $pdo->query("SELECT filename FROM _migrations")->fetchAll(PDO::FETCH_COLUMN);
$cmd = $argv[1] ?? 'status';

function run_sql_file(PDO $pdo, string $file) {
  $sql = file_get_contents($file);
  $pdo->beginTransaction();
  try {
    $pdo->exec($sql);
    $pdo->commit();
    echo "✅ ".basename($file)."\n";
  } catch (Throwable $e) {
    $pdo->rollBack();
    echo "❌ ".basename($file)." -> ".$e->getMessage()."\n";
    exit(1);
  }
}

if ($cmd === 'status') {
  echo "📋 Migration Status (".$pdo->query("SELECT DATABASE()")->fetchColumn()."):\n";
  foreach ($files as $f) {
    $b = basename($f);
    echo (in_array($b,$applied) ? " [✓] " : " [ ] ") . $b . "\n";
  }
  exit;
}
if ($cmd === 'up') {
  foreach ($files as $f) {
    $b = basename($f);
    if (in_array($b,$applied)) continue;
    echo "▶️  Applying $b...\n";
    run_sql_file($pdo,$f);
    $stmt = $pdo->prepare("INSERT INTO _migrations(filename) VALUES(?)");
    $stmt->execute([$b]);
  }
  echo "🎉 All pending migrations applied.\n";
  exit;
}
echo "Usage: php database/migration_runner.php [status|up]\n";
