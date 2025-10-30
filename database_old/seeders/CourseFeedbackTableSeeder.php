<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CourseFeedbackTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('course_feedback')->delete();
        
        \DB::table('course_feedback')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 1,
                'course_id' => 1,
                'rating' => 5,
                'feedback' => 'Excellent course! The instructors were very knowledgeable and the hands-on projects were really helpful.',
                'created_at' => '2024-07-20 16:00:00',
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 3,
                'student_id' => 3,
                'course_id' => 3,
                'rating' => 5,
                'feedback' => 'Outstanding Java course. The Spring Boot module was particularly useful for my career.',
                'created_at' => '2024-06-25 18:00:00',
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 4,
                'student_id' => 5,
                'course_id' => 5,
                'rating' => 4,
                'feedback' => 'Good course for mobile development. The React Native concepts were well explained.',
                'created_at' => '2024-06-15 19:00:00',
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 7,
                'student_id' => 1,
                'course_id' => 2,
                'rating' => 5,
                'feedback' => 'Excellent course! Very useful.',
                'created_at' => '2025-08-29 13:20:48',
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 9,
                'student_id' => 5,
                'course_id' => 2,
                'rating' => 5,
                'feedback' => 'Excellent course! Very useful.',
                'created_at' => '2025-08-29 13:42:57',
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 10,
                'student_id' => 6,
                'course_id' => 2,
                'rating' => 5,
                'feedback' => 'This course was excellent!',
                'created_at' => '2025-09-01 00:04:08',
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 11,
                'student_id' => 1,
                'course_id' => 1,
                'rating' => 5,
                'feedback' => 'This course was very helpful!',
                'created_at' => '2025-09-03 17:25:53',
                'admin_action' => 'pending',
            ),
            7 => 
            array (
                'id' => 12,
                'student_id' => 1,
                'course_id' => 1,
                'rating' => 5,
                'feedback' => 'This course was very helpful!',
                'created_at' => '2025-09-03 17:26:57',
                'admin_action' => 'pending',
            ),
            8 => 
            array (
                'id' => 14,
                'student_id' => 3,
                'course_id' => 1,
                'rating' => 5,
                'feedback' => 'This course was very helpful!',
                'created_at' => '2025-09-03 17:27:59',
                'admin_action' => 'pending',
            ),
            9 => 
            array (
                'id' => 15,
                'student_id' => 3,
                'course_id' => 1,
                'rating' => 5,
                'feedback' => 'This course was very helpful!',
                'created_at' => '2025-09-03 17:28:30',
                'admin_action' => 'pending',
            ),
            10 => 
            array (
                'id' => 16,
                'student_id' => 7,
                'course_id' => 5,
                'rating' => 5,
                'feedback' => 'Excellent course! Very informative and well-structured.',
                'created_at' => '2025-09-29 12:30:40',
                'admin_action' => 'approved',
            ),
            11 => 
            array (
                'id' => 17,
                'student_id' => 7,
                'course_id' => 10,
                'rating' => 5,
                'feedback' => 'Excellent course! Very informative and well-structured.',
                'created_at' => '2025-09-29 12:32:31',
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}