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
                'category_name' => 'Technical',
                'created_at' => '2025-10-22 17:58:32',
            ),
            1 => 
            array (
                'id' => 2,
                'category_name' => 'Non-Technical',
                'created_at' => '2025-10-22 17:58:32',
            ),
            2 => 
            array (
                'id' => 3,
                'category_name' => 'Vocational',
                'created_at' => '2025-10-22 17:58:32',
            ),
            3 => 
            array (
                'id' => 4,
                'category_name' => 'Professional',
                'created_at' => '2025-10-22 17:58:32',
            ),
        ));
        
        
    }
}