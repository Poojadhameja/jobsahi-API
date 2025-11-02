<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InterviewPanelTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('interview_panel')->delete();
        
        \DB::table('interview_panel')->insert(array (
            0 => 
            array (
                'id' => 4,
                'interview_id' => 1,
                'panelist_name' => '5',
                'feedback' => 'Candidate has strong technical knowledge',
                'rating' => 4,
                'created_at' => '2025-09-03 16:35:51',
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 5,
                'interview_id' => 1,
                'panelist_name' => '6',
                'feedback' => 'Candidate has strong technical knowledge',
                'rating' => 4,
                'created_at' => '2025-09-01 22:19:26',
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}