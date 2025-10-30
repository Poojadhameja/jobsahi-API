<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SkillTestsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('skill_tests')->delete();
        
        \DB::table('skill_tests')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 6,
                'test_platform' => 'HackerRank',
                'test_name' => 'Java Basics',
                'score' => NULL,
                'max_score' => 100,
                'completed_at' => NULL,
                'badge_awarded' => 0,
                'passed' => 0,
                'created_at' => '2025-10-13 17:00:54',
                'modified_at' => '2025-10-13 17:00:54',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 2,
                'student_id' => 6,
                'test_platform' => 'HackerRank',
                'test_name' => 'Python Basics',
                'score' => NULL,
                'max_score' => 100,
                'completed_at' => NULL,
                'badge_awarded' => 0,
                'passed' => 0,
                'created_at' => '2025-10-24 23:35:19',
                'modified_at' => '2025-10-24 23:35:19',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}