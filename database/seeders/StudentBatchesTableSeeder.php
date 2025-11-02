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
                'assignment_reason' => 'Assigning for new batch start',
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'student_id' => 1,
                'batch_id' => 1,
                'assignment_reason' => 'Assigning for new batch start',
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}