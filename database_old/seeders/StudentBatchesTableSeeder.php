<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StudentBatchesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('student_batches')->delete();
        
        \DB::table('student_batches')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 1,
                'batch_id' => 1,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 3,
                'student_id' => 3,
                'batch_id' => 4,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            2 => 
            array (
                'id' => 4,
                'student_id' => 4,
                'batch_id' => 2,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            3 => 
            array (
                'id' => 5,
                'student_id' => 5,
                'batch_id' => 6,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            4 => 
            array (
                'id' => 6,
                'student_id' => 7,
                'batch_id' => 3,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            5 => 
            array (
                'id' => 7,
                'student_id' => 1,
                'batch_id' => 2,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            6 => 
            array (
                'id' => 8,
                'student_id' => 3,
                'batch_id' => 2,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            7 => 
            array (
                'id' => 9,
                'student_id' => 4,
                'batch_id' => 2,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            8 => 
            array (
                'id' => 10,
                'student_id' => 5,
                'batch_id' => 2,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
            9 => 
            array (
                'id' => 11,
                'student_id' => 7,
                'batch_id' => 2,
                'assignment_reason' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}