<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StudentCourseEnrollmentsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('student_course_enrollments')->delete();
        
        \DB::table('student_course_enrollments')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 1,
                'course_id' => 1,
                'enrollment_date' => '2025-10-30 15:55:04',
                'status' => 'enrolled',
                'created_at' => '2025-10-30 15:55:04',
                'modified_at' => '2025-10-30 15:55:04',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'student_id' => 1,
                'course_id' => 5,
                'enrollment_date' => '2025-10-30 16:01:41',
                'status' => 'enrolled',
                'created_at' => '2025-10-30 16:01:41',
                'modified_at' => '2025-10-30 16:02:02',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            2 => 
            array (
                'id' => 3,
                'student_id' => 1,
                'course_id' => 5,
                'enrollment_date' => '2025-10-30 16:08:05',
                'status' => 'enrolled',
                'created_at' => '2025-10-30 16:08:05',
                'modified_at' => '2025-10-30 16:08:05',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            3 => 
            array (
                'id' => 4,
                'student_id' => 1,
                'course_id' => 5,
                'enrollment_date' => '2025-10-30 16:09:32',
                'status' => 'enrolled',
                'created_at' => '2025-10-30 16:09:32',
                'modified_at' => '2025-10-30 16:09:32',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            4 => 
            array (
                'id' => 5,
                'student_id' => 1,
                'course_id' => 5,
                'enrollment_date' => '2025-10-30 16:10:15',
                'status' => 'enrolled',
                'created_at' => '2025-10-30 16:10:15',
                'modified_at' => '2025-10-30 16:10:15',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}