<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CourseFeedbackTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('course_feedback')->delete();
        
        
        
    }
}