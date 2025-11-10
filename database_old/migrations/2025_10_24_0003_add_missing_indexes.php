<?php
class AddMissingIndexes {
    public function up(PDO $pdo) {
        $indexes = [
            ['table' => 'student_profiles', 'index' => 'idx_student_user_id', 'col' => 'user_id'],
            ['table' => 'jobs', 'index' => 'idx_jobs_recruiter_id', 'col' => 'recruiter_id'],
            ['table' => 'applications', 'index' => 'idx_applications_job_id', 'col' => 'job_id'],
        ];

        foreach ($indexes as $idx) {
            try {
                $pdo->exec("
                    CREATE INDEX IF NOT EXISTS {$idx['index']} 
                    ON {$idx['table']}({$idx['col']});
                ");
                echo "✅ Index {$idx['index']} added to {$idx['table']}." . PHP_EOL;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    echo "ℹ️ Index {$idx['index']} already exists, skipped." . PHP_EOL;
                } else {
                    throw $e;
                }
            }
        }
    }
}
