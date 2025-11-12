<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JobRecommendationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('job_recommendations')->delete();
        
        \DB::table('job_recommendations')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 1,
                'job_id' => 1,
                'source' => 'ai',
                'score' => '95.50',
                'created_at' => '2024-08-01 09:00:00',
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 2,
                'student_id' => 1,
                'job_id' => 4,
                'source' => 'ai',
                'score' => '85.30',
                'created_at' => '2024-08-01 09:01:00',
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 4,
                'student_id' => 3,
                'job_id' => 4,
                'source' => 'ai',
                'score' => '88.90',
                'created_at' => '2024-08-03 09:00:00',
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 5,
                'student_id' => 4,
                'job_id' => 2,
                'source' => 'ai',
                'score' => '92.40',
                'created_at' => '2024-08-04 09:00:00',
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 6,
                'student_id' => 5,
                'job_id' => 5,
                'source' => 'ai',
                'score' => '89.60',
                'created_at' => '2024-08-05 09:00:00',
                'admin_action' => 'approved',
            ),
            5 => 
            array (
                'id' => 7,
                'student_id' => 6,
                'job_id' => 6,
                'source' => 'ai',
                'score' => '94.20',
                'created_at' => '2024-08-06 09:00:00',
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}