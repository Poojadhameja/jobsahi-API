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
        
        
        
    }
}