<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InterviewsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('interviews')->delete();
        
        \DB::table('interviews')->insert(array (
            0 => 
            array (
                'id' => 1,
                'application_id' => 1,
                'scheduled_at' => '2024-08-20 14:00:00',
                'mode' => 'online',
                'location' => 'Google Meet Link: meet.google.com/xyz-abc-def',
                'status' => 'scheduled',
                'feedback' => '',
                'created_at' => '2024-08-15 10:00:00',
                'modified_at' => '2025-08-26 13:35:52',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 3,
                'application_id' => 5,
                'scheduled_at' => '2024-08-22 10:00:00',
                'mode' => 'offline',
                'location' => 'TechCorp Solutions Office, Bangalore',
                'status' => 'scheduled',
                'feedback' => '',
                'created_at' => '2024-08-16 12:00:00',
                'modified_at' => '2025-08-26 13:35:52',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 4,
                'application_id' => 4,
                'scheduled_at' => '2025-09-10 10:00:00',
                'mode' => 'online',
                'location' => 'Zoom',
                'status' => 'scheduled',
                'feedback' => 'Candidate is promising',
                'created_at' => '2025-09-01 16:27:25',
                'modified_at' => '2025-09-01 17:20:20',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 5,
                'application_id' => 4,
                'scheduled_at' => '2025-09-05 10:00:00',
                'mode' => 'online',
                'location' => 'Zoom',
                'status' => 'scheduled',
                'feedback' => '',
                'created_at' => '2025-09-01 16:27:40',
                'modified_at' => '2025-09-01 16:27:40',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 6,
                'application_id' => 4,
                'scheduled_at' => '2025-09-05 10:00:00',
                'mode' => 'online',
                'location' => 'Zoom',
                'status' => 'scheduled',
                'feedback' => '',
                'created_at' => '2025-09-03 22:54:52',
                'modified_at' => '2025-09-03 22:54:52',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 7,
                'application_id' => 4,
                'scheduled_at' => '2025-09-05 10:00:00',
                'mode' => 'online',
                'location' => 'Zoom',
                'status' => 'scheduled',
                'feedback' => '',
                'created_at' => '2025-09-03 23:00:13',
                'modified_at' => '2025-09-03 23:00:13',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}