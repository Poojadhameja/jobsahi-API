<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ApplicationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('applications')->delete();
        
        \DB::table('applications')->insert(array (
            0 => 
            array (
                'id' => 1,
                'job_id' => 1,
                'interview_id' => NULL,
                'student_id' => 1,
                'job_selected' => 1,
                'status' => 'applied',
                'applied_at' => '2025-10-30 12:57:41',
                'cover_letter' => 'I am excited to apply for this position. My training and technical skills match the job requirements well.',
                'created_at' => '2025-10-30 12:57:41',
                'modified_at' => '2025-10-31 14:03:50',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}