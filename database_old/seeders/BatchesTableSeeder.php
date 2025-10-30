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
            'name' => 'FSWD-Jan-2024 (Final Approved)',
                'batch_time_slot' => '10:00 AM - 12:00 AM',
                'start_date' => '2024-01-15',
                'end_date' => '2024-07-15',
                'media' => '["uploads\\/batches\\/syllabus.pdf"]',
                'instructor_id' => 5,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'course_id' => 1,
            'name' => 'FSWD-Mar-2024 (Final Approved)',
                'batch_time_slot' => '11:00 AM - 1:00 AM',
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => '["uploads\\/batches\\/syllabus1.pdf"]',
                'instructor_id' => 4,
                'admin_action' => '',
            ),
            2 => 
            array (
                'id' => 3,
                'course_id' => 2,
                'name' => 'DS-Feb-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-02-01',
                'end_date' => '2024-06-01',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 4,
                'course_id' => 3,
                'name' => 'Java-Jan-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-01-20',
                'end_date' => '2024-06-20',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 5,
                'course_id' => 3,
                'name' => 'DS-Feb-2024-Updated',
                'batch_time_slot' => NULL,
                'start_date' => '2024-02-05',
                'end_date' => '2024-08-05',
                'media' => NULL,
                'instructor_id' => 3,
                'admin_action' => 'approved',
            ),
            5 => 
            array (
                'id' => 6,
                'course_id' => 4,
                'name' => 'RN-Feb-2024-Final',
                'batch_time_slot' => NULL,
                'start_date' => '2024-02-15',
                'end_date' => '2024-08-15',
                'media' => NULL,
                'instructor_id' => 2,
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 8,
                'course_id' => 5,
                'name' => 'RN-Feb-2024-Final',
                'batch_time_slot' => NULL,
                'start_date' => '2024-02-15',
                'end_date' => '2024-08-15',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'pending',
            ),
            7 => 
            array (
                'id' => 9,
                'course_id' => 2,
                'name' => 'FSWD-Mar-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'pending',
            ),
            8 => 
            array (
                'id' => 10,
                'course_id' => 2,
                'name' => 'FSWD-Mar-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'pending',
            ),
            9 => 
            array (
                'id' => 11,
                'course_id' => 2,
                'name' => 'FSWD-Mar-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'pending',
            ),
            10 => 
            array (
                'id' => 12,
                'course_id' => 2,
            'name' => 'FSWD-Mar-2024 (Final Approved)',
                'batch_time_slot' => '09:00 AM - 11:00 AM',
                'start_date' => '2025-08-14',
                'end_date' => '2026-01-16',
                'media' => '["uploads\\/batches\\/syllabus.pdf"]',
                'instructor_id' => 5,
                'admin_action' => 'approved',
            ),
            11 => 
            array (
                'id' => 13,
                'course_id' => 2,
                'name' => 'FSWD-Mar-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => NULL,
                'instructor_id' => 2,
                'admin_action' => 'pending',
            ),
            12 => 
            array (
                'id' => 14,
                'course_id' => 10,
                'name' => 'FSWD-Mar-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => NULL,
                'instructor_id' => 6,
                'admin_action' => 'pending',
            ),
            13 => 
            array (
                'id' => 15,
                'course_id' => 10,
                'name' => 'FSWD-Mar-2024',
                'batch_time_slot' => NULL,
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-01',
                'media' => NULL,
                'instructor_id' => 1,
                'admin_action' => 'approved',
            ),
            14 => 
            array (
                'id' => 16,
                'course_id' => 2,
                'name' => 'Full Stack Web Development - Updated',
                'batch_time_slot' => '02:00 PM - 04:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => '["uploads\\/batches\\/updated.pdf"]',
                'instructor_id' => 5,
                'admin_action' => 'approved',
            ),
            15 => 
            array (
                'id' => 17,
                'course_id' => 1,
                'name' => 'Full Stack Web Development Batch - Nov 2025',
                'batch_time_slot' => '10:00 AM - 12:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => '["uploads\\/batches\\/syllabus.pdf","uploads\\/batches\\/schedule.png"]',
                'instructor_id' => 5,
                'admin_action' => 'approved',
            ),
            16 => 
            array (
                'id' => 18,
                'course_id' => 6,
                'name' => 'DevOps Engineering Batch - Nov 2025',
                'batch_time_slot' => '10:00 AM - 12:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => '["uploads\\/batches\\/syllabus.pdf","uploads\\/batches\\/schedule.png"]',
                'instructor_id' => 5,
                'admin_action' => 'approved',
            ),
            17 => 
            array (
                'id' => 19,
                'course_id' => 6,
                'name' => 'DevOps Engineering Batch - Nov 2025',
                'batch_time_slot' => '10:00 AM - 12:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => '["uploads\\/batches\\/syllabus.pdf","uploads\\/batches\\/schedule.png"]',
                'instructor_id' => 5,
                'admin_action' => 'approved',
            ),
            18 => 
            array (
                'id' => 20,
                'course_id' => 6,
                'name' => 'DevOps Engineering Batch - Nov 2025',
                'batch_time_slot' => '10:00 AM - 12:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => '["uploads\\/batches\\/syllabus.pdf","uploads\\/batches\\/schedule.png"]',
                'instructor_id' => 5,
                'admin_action' => 'approved',
            ),
            19 => 
            array (
                'id' => 21,
                'course_id' => 6,
                'name' => 'DevOps Engineering Batch - Nov 2025',
                'batch_time_slot' => '10:00 AM - 12:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => '["uploads\\/batches\\/syllabus.pdf","uploads\\/batches\\/schedule.png"]',
                'instructor_id' => 1,
                'admin_action' => 'approved',
            ),
            20 => 
            array (
                'id' => 22,
                'course_id' => 7,
                'name' => 'DevOps Engineering Batch - Nov 2025',
                'batch_time_slot' => '10:00 AM - 12:00 PM',
                'start_date' => '2025-11-10',
                'end_date' => '2026-05-10',
                'media' => '["uploads\\/batches\\/syllabus.pdf","uploads\\/batches\\/schedule.png"]',
                'instructor_id' => 1,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}