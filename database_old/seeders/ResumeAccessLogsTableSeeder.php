<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ResumeAccessLogsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('resume_access_logs')->delete();
        
        \DB::table('resume_access_logs')->insert(array (
            0 => 
            array (
                'id' => 1,
                'recruiter_id' => 1,
                'student_id' => 1,
                'viewed_at' => '2024-08-15 10:15:00',
            ),
            1 => 
            array (
                'id' => 2,
                'recruiter_id' => 1,
                'student_id' => 3,
                'viewed_at' => '2024-08-18 16:20:00',
            ),
            2 => 
            array (
                'id' => 4,
                'recruiter_id' => 3,
                'student_id' => 5,
                'viewed_at' => '2024-08-17 09:30:00',
            ),
            3 => 
            array (
                'id' => 5,
                'recruiter_id' => 2,
                'student_id' => 4,
                'viewed_at' => '2024-08-19 11:45:00',
            ),
        ));
        
        
    }
}