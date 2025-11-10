<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CoursePaymentsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('course_payments')->delete();
        
        
        
    }
}