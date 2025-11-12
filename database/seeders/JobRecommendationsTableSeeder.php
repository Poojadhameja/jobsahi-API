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
        
        
        
    }
}