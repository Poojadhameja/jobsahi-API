<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SkillAttemptsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('skill_attempts')->delete();
        
        \DB::table('skill_attempts')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 6,
                'test_id' => 1,
                'question_id' => 1,
                'selected_option' => 'D',
                'is_correct' => 1,
                'attempt_number' => 1,
                'time_taken_seconds' => 30,
                'answered_at' => '2025-10-13 17:51:39',
            ),
            1 => 
            array (
                'id' => 2,
                'student_id' => 6,
                'test_id' => 1,
                'question_id' => 2,
                'selected_option' => 'B',
                'is_correct' => 1,
                'attempt_number' => 1,
                'time_taken_seconds' => 45,
                'answered_at' => '2025-10-24 23:37:56',
            ),
        ));
        
        
    }
}