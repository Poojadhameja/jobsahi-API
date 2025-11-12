<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JobViewsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('job_views')->delete();
        
        \DB::table('job_views')->insert(array (
            0 => 
            array (
                'id' => 1,
                'job_id' => 1,
                'student_id' => 1,
                'viewed_at' => '2024-08-01 10:30:00',
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 2,
                'job_id' => 1,
                'student_id' => 2,
                'viewed_at' => '2024-08-01 11:00:00',
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 3,
                'job_id' => 1,
                'student_id' => 3,
                'viewed_at' => '2024-08-01 12:00:00',
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 4,
                'job_id' => 2,
                'student_id' => 1,
                'viewed_at' => '2024-08-02 13:00:00',
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 5,
                'job_id' => 2,
                'student_id' => 4,
                'viewed_at' => '2024-08-02 14:00:00',
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 6,
                'job_id' => 3,
                'student_id' => 2,
                'viewed_at' => '2024-08-03 15:00:00',
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 7,
                'job_id' => 3,
                'student_id' => 5,
                'viewed_at' => '2024-08-03 16:00:00',
                'admin_action' => 'pending',
            ),
            7 => 
            array (
                'id' => 8,
                'job_id' => 4,
                'student_id' => 3,
                'viewed_at' => '2024-08-04 17:00:00',
                'admin_action' => 'pending',
            ),
            8 => 
            array (
                'id' => 9,
                'job_id' => 5,
                'student_id' => 5,
                'viewed_at' => '2024-08-05 18:00:00',
                'admin_action' => 'pending',
            ),
            9 => 
            array (
                'id' => 10,
                'job_id' => 6,
                'student_id' => 6,
                'viewed_at' => '2024-08-06 19:00:00',
                'admin_action' => 'pending',
            ),
            10 => 
            array (
                'id' => 11,
                'job_id' => 4,
                'student_id' => 7,
                'viewed_at' => '2025-08-27 12:49:30',
                'admin_action' => 'pending',
            ),
            11 => 
            array (
                'id' => 12,
                'job_id' => 4,
                'student_id' => 19,
                'viewed_at' => '2025-08-27 12:49:50',
                'admin_action' => 'pending',
            ),
            12 => 
            array (
                'id' => 13,
                'job_id' => 5,
                'student_id' => 18,
                'viewed_at' => '2025-08-27 13:37:17',
                'admin_action' => 'pending',
            ),
            13 => 
            array (
                'id' => 14,
                'job_id' => 1,
                'student_id' => 5,
                'viewed_at' => '2025-08-30 22:00:03',
                'admin_action' => 'pending',
            ),
            14 => 
            array (
                'id' => 15,
                'job_id' => 2,
                'student_id' => 5,
                'viewed_at' => '2025-08-30 22:00:13',
                'admin_action' => 'pending',
            ),
            15 => 
            array (
                'id' => 16,
                'job_id' => 2,
                'student_id' => 7,
                'viewed_at' => '2025-08-30 22:00:54',
                'admin_action' => 'pending',
            ),
            16 => 
            array (
                'id' => 17,
                'job_id' => 5,
                'student_id' => 5,
                'viewed_at' => '2025-08-31 17:31:52',
                'admin_action' => 'pending',
            ),
            17 => 
            array (
                'id' => 18,
                'job_id' => 3,
                'student_id' => 6,
                'viewed_at' => '2025-09-03 20:11:30',
                'admin_action' => 'pending',
            ),
            18 => 
            array (
                'id' => 19,
                'job_id' => 6,
                'student_id' => 6,
                'viewed_at' => '2025-09-03 20:18:54',
                'admin_action' => 'pending',
            ),
            19 => 
            array (
                'id' => 20,
                'job_id' => 8,
                'student_id' => 6,
                'viewed_at' => '0000-00-00 00:00:00',
                'admin_action' => 'pending',
            ),
            20 => 
            array (
                'id' => 21,
                'job_id' => 9,
                'student_id' => 6,
                'viewed_at' => '2025-09-03 20:43:39',
                'admin_action' => 'pending',
            ),
            21 => 
            array (
                'id' => 22,
                'job_id' => 11,
                'student_id' => 6,
                'viewed_at' => '2025-09-03 20:44:18',
                'admin_action' => 'pending',
            ),
            22 => 
            array (
                'id' => 23,
                'job_id' => 12,
                'student_id' => 6,
                'viewed_at' => '2025-09-03 20:48:35',
                'admin_action' => 'pending',
            ),
            23 => 
            array (
                'id' => 24,
                'job_id' => 15,
                'student_id' => 48,
                'viewed_at' => '2025-09-25 15:54:14',
                'admin_action' => 'pending',
            ),
            24 => 
            array (
                'id' => 25,
                'job_id' => 13,
                'student_id' => 48,
                'viewed_at' => '2025-09-25 15:55:50',
                'admin_action' => 'pending',
            ),
            25 => 
            array (
                'id' => 26,
                'job_id' => 12,
                'student_id' => 48,
                'viewed_at' => '2025-09-25 15:59:49',
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}