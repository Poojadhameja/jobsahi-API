<?php
/**
 * JobSahi Migration Runner (Scratch Build)
 * Usage:
 *   php database/migration_runner.php status
 *   php database/migration_runner.php up
 */
require_once __DIR__ . '/../api/db.php'; // ← uses same creds as APIs

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  die("❌ DB connect failed: ".$e->getMessage()."\n");
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
