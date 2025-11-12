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
                'enrollment_date' => '2024-01-15 10:00:00',
                'status' => 'completed',
                'created_at' => '2024-01-15 10:00:00',
                'modified_at' => '2025-10-24 15:33:45',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 3,
                'student_id' => 3,
                'course_id' => 3,
                'enrollment_date' => '2024-01-20 12:00:00',
                'status' => 'completed',
                'created_at' => '2024-01-20 12:00:00',
                'modified_at' => '2025-10-24 15:14:26',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            2 => 
            array (
                'id' => 4,
                'student_id' => 4,
                'course_id' => 1,
                'enrollment_date' => '2024-03-01 13:00:00',
                'status' => 'completed',
                'created_at' => '2024-03-01 13:00:00',
                'modified_at' => '2025-10-24 14:53:18',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            3 => 
            array (
                'id' => 5,
                'student_id' => 5,
                'course_id' => 5,
                'enrollment_date' => '2024-02-10 14:00:00',
                'status' => 'completed',
                'created_at' => '2024-02-10 14:00:00',
                'modified_at' => '2025-10-24 14:53:24',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            4 => 
            array (
                'id' => 6,
                'student_id' => 6,
                'course_id' => 2,
                'enrollment_date' => '2024-02-01 15:00:00',
                'status' => 'completed',
                'created_at' => '2024-02-01 15:00:00',
                'modified_at' => '2025-10-24 15:33:54',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            5 => 
            array (
                'id' => 15,
                'student_id' => 6,
                'course_id' => 1,
                'enrollment_date' => '2025-09-01 00:08:31',
                'status' => 'completed',
                'created_at' => '2025-09-01 00:08:31',
                'modified_at' => '2025-10-24 15:36:21',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            6 => 
            array (
                'id' => 16,
                'student_id' => 5,
                'course_id' => 1,
                'enrollment_date' => '2025-09-03 17:14:14',
                'status' => 'completed',
                'created_at' => '2025-09-03 17:14:14',
                'modified_at' => '2025-10-24 15:36:33',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            7 => 
            array (
                'id' => 18,
                'student_id' => 7,
                'course_id' => 10,
                'enrollment_date' => '2025-09-29 12:44:10',
                'status' => 'enrolled',
                'created_at' => '2025-09-29 12:44:10',
                'modified_at' => '2025-10-24 15:48:44',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            8 => 
            array (
                'id' => 20,
                'student_id' => 7,
                'course_id' => 11,
                'enrollment_date' => '2025-09-29 13:03:50',
                'status' => 'completed',
                'created_at' => '2025-09-29 13:03:50',
                'modified_at' => '2025-10-24 15:36:52',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            9 => 
            array (
                'id' => 21,
                'student_id' => 1,
                'course_id' => 5,
                'enrollment_date' => '2025-10-24 00:28:34',
                'status' => 'completed',
                'created_at' => '2025-10-24 00:28:34',
                'modified_at' => '2025-10-24 15:37:04',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            10 => 
            array (
                'id' => 22,
                'student_id' => 3,
                'course_id' => 5,
                'enrollment_date' => '2025-10-24 00:28:34',
                'status' => 'completed',
                'created_at' => '2025-10-24 00:28:34',
                'modified_at' => '2025-10-24 15:37:12',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            11 => 
            array (
                'id' => 23,
                'student_id' => 4,
                'course_id' => 5,
                'enrollment_date' => '2025-10-24 00:28:34',
                'status' => 'completed',
                'created_at' => '2025-10-24 00:28:34',
                'modified_at' => '2025-10-24 00:33:25',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            12 => 
            array (
                'id' => 24,
                'student_id' => 5,
                'course_id' => 5,
                'enrollment_date' => '2025-10-24 13:27:58',
                'status' => 'enrolled',
                'created_at' => '2025-10-24 13:27:58',
                'modified_at' => '2025-12-31 15:36:33',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            13 => 
            array (
                'id' => 25,
                'student_id' => 6,
                'course_id' => 5,
                'enrollment_date' => '2025-10-24 13:27:58',
                'status' => 'enrolled',
                'created_at' => '2025-10-24 13:27:58',
                'modified_at' => '2026-02-19 15:36:21',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}