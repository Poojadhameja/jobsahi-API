<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JobViewsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('job_views')->delete();
        
        
        
    }
}