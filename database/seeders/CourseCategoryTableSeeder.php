<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CourseCategoryTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('course_category')->delete();
        
        \DB::table('course_category')->insert(array (
            0 => 
            array (
                'id' => 1,
                'category_name' => 'Technical',
                'created_at' => '2025-10-29 11:34:39',
            ),
            1 => 
            array (
                'id' => 2,
                'category_name' => 'Non-Technical',
                'created_at' => '2025-10-29 14:29:40',
            ),
            2 => 
            array (
                'id' => 3,
                'category_name' => 'Electrical',
                'created_at' => '2025-10-29 14:38:53',
            ),
            3 => 
            array (
                'id' => 4,
                'category_name' => 'MBBS',
                'created_at' => '2025-10-29 15:57:15',
            ),
        ));
        
        
    }
}