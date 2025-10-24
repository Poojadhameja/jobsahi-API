<?php
class SeedActivityLog {
    public function up(PDO $pdo) {
        try {
            $pdo->exec("
                INSERT INTO activity_logs (user_id, action, reference_table, reference_id, created_at)
                VALUES (1, 'System Migration Executed', 'migration', 0, NOW());
            ");
            echo '🌱 Activity log seeder executed.' . PHP_EOL;
        } catch (PDOException $e) {
            echo 'ℹ️ Seeder skipped: ' . $e->getMessage() . PHP_EOL;
        }
    }
}
