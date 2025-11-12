<?php
class FixForeignKeysForCourses {
    public function up(PDO $pdo) {
        try {
            // Ensure institute_id exists as unsigned INT
            $pdo->exec("
                ALTER TABLE courses
                MODIFY institute_id INT(10) UNSIGNED NULL;
            ");

            // Add index if missing
            $pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_courses_institute_id ON courses(institute_id);
            ");

            // Add FK if missing
            $pdo->exec("
                ALTER TABLE courses
                ADD CONSTRAINT fk_course_institute
                FOREIGN KEY (institute_id)
                REFERENCES institute_profiles(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE;
            ");
            echo '✅ Foreign key fk_course_institute added successfully.' . PHP_EOL;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo 'ℹ️ Foreign key already exists, skipped.' . PHP_EOL;
            } else {
                throw $e;
            }
        }
    }
}
