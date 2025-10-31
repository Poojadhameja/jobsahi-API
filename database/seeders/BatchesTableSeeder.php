<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BatchesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('batches')->delete();
        
        \DB::table('batches')->insert(array (
            0 => 
            array (
                'id' => 1,
                'course_id' => 1,
            'name' => 'Assistant Electrician - October 2025 (Morning Batch)',
                'batch_time_slot' => '11:00 AM - 1:00 AM',
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => NULL,
                'instructor_id' => 2,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'course_id' => 1,
            'name' => 'Assistant Electrician - March 2025 (Morning Batch)',
                'batch_time_slot' => '9:00 AM - 11:00 AM',
                'start_date' => '2025-03-01',
                'end_date' => '2025-09-01',
                'media' => NULL,
                'instructor_id' => 2,
                'admin_action' => 'approved',
            ),
            2 => 
            array (
                'id' => 3,
                'course_id' => 1,
                'name' => 'DevOps Engineering Batch - Nov 2025',
                'batch_time_slot' => '10:00 AM - 12:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}