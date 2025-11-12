<?php

class Add_Assignment_Reason_To_Courses
{
    public function up($pdo)
    {
        $sql = "ALTER TABLE courses ADD COLUMN assignment_reason VARCHAR(255) NULL AFTER course_title;";
        $pdo->exec($sql);
        echo "→ Column 'assignment_reason' added to courses ✅\n";
    }

    public function down($pdo)
    {
        $sql = "ALTER TABLE courses DROP COLUMN assignment_reason;";
        $pdo->exec($sql);
        echo "→ Column 'assignment_reason' removed from courses ❌\n";
    }
}
