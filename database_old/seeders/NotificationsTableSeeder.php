<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NotificationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('notifications')->delete();
        
        \DB::table('notifications')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_id' => 49,
                'receiver_id' => NULL,
                'message' => 'System maintenance at 10 PM tonight',
                'type' => 'system',
                'is_read' => 0,
                'created_at' => '2025-09-30 16:39:11',
                'received_role' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'user_id' => 50,
                'receiver_id' => NULL,
                'message' => 'New college event: AI Workshop on 5th Oct!',
                'type' => 'general',
                'is_read' => 1,
                'created_at' => '2025-09-30 16:43:19',
                'received_role' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'user_id' => 49,
                'receiver_id' => NULL,
                'message' => 'New college event: AI Workshop on 5th Oct!',
                'type' => 'general',
                'is_read' => 0,
                'created_at' => '2025-10-01 17:26:56',
                'received_role' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'user_id' => 0,
                'receiver_id' => 49,
                'message' => 'Candidate Mike Johnson has been shortlisted for Frontend Developer role',
                'type' => 'general',
                'is_read' => 0,
                'created_at' => '2025-10-01 22:19:49',
                'received_role' => 'recruiter',
            ),
        ));
        
        
    }
}