<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JobCategoryTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('job_category')->delete();
        
        \DB::table('job_category')->insert(array (
            0 => 
            array (
                'id' => 1,
                'category_name' => 'Civil Engineering',
                'created_at' => '2025-10-30 02:18:28',
            ),
            1 => 
            array (
                'id' => 2,
                'category_name' => 'Electrical Engineering',
                'created_at' => '2025-10-30 02:22:19',
            ),
        ));
        
        
    }
}