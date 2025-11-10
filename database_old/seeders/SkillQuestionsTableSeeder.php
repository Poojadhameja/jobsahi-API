<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SkillQuestionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('skill_questions')->delete();
        
        \DB::table('skill_questions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'test_id' => 1,
                'question_text' => 'Which HTML tag is used to create a hyperlink?',
                'option_a' => '<link>',
                'option_b' => '<url>',
                'option_c' => '<href>',
                'option_d' => '<a>',
                'correct_option' => 'D',
                'created_at' => '2025-10-13 17:07:23',
            ),
            1 => 
            array (
                'id' => 2,
                'test_id' => 2,
            'question_text' => 'What is the output of print(2 ** 3) in Python?',
                'option_a' => '6',
                'option_b' => '8',
                'option_c' => '9',
                'option_d' => '23',
                'correct_option' => 'B',
                'created_at' => '2025-10-24 23:36:53',
            ),
        ));
        
        
    }
}