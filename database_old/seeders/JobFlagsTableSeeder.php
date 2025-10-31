<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JobFlagsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('job_flags')->delete();
        
        \DB::table('job_flags')->insert(array (
            0 => 
            array (
                'id' => 1,
                'job_id' => 4,
                'flagged_by' => 9,
                'reason' => 'Salary seems too low for the skill requirements',
                'reviewed' => 1,
                'created_at' => '2025-08-26 13:58:06',
                'admin_action' => '',
            ),
        ));
        
        
    }
}