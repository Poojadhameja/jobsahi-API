<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MessagesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('messages')->delete();
        
        \DB::table('messages')->insert(array (
            0 => 
            array (
                'id' => 1,
                'sender_id' => 49,
                'sender_role' => 'recruiter',
                'receiver_id' => 3,
                'receiver_role' => '',
                'message' => 'Hello, this is a test message!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 16:33:01',
            ),
            1 => 
            array (
                'id' => 2,
                'sender_id' => 49,
                'sender_role' => 'recruiter',
                'receiver_id' => 4,
                'receiver_role' => '',
                'message' => 'Hello, this is a test message!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 16:45:12',
            ),
            2 => 
            array (
                'id' => 3,
                'sender_id' => 49,
                'sender_role' => 'recruiter',
                'receiver_id' => 4,
                'receiver_role' => 'student',
                'message' => 'Hello, this is a test message!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 16:50:09',
            ),
            3 => 
            array (
                'id' => 4,
                'sender_id' => 49,
                'sender_role' => 'recruiter',
                'receiver_id' => 5,
                'receiver_role' => 'student',
                'message' => 'Hello, this is a test message!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 16:50:16',
            ),
            4 => 
            array (
                'id' => 5,
                'sender_id' => 6,
                'sender_role' => 'admin',
                'receiver_id' => 50,
                'receiver_role' => 'student',
                'message' => 'Hello, this is a test message!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 17:04:09',
            ),
            5 => 
            array (
                'id' => 6,
                'sender_id' => 6,
                'sender_role' => 'admin',
                'receiver_id' => 50,
                'receiver_role' => 'institute',
                'message' => 'Hello, this is a test message!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 17:05:52',
            ),
            6 => 
            array (
                'id' => 7,
                'sender_id' => 50,
                'sender_role' => 'institute',
                'receiver_id' => 48,
                'receiver_role' => 'institute',
                'message' => 'Hello Student, this is a message from Institute!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 17:10:59',
            ),
            7 => 
            array (
                'id' => 8,
                'sender_id' => 50,
                'sender_role' => 'institute',
                'receiver_id' => 48,
                'receiver_role' => 'student',
                'message' => 'Hello Student, this is a message from Institute!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 17:11:20',
            ),
            8 => 
            array (
                'id' => 9,
                'sender_id' => 48,
                'sender_role' => 'student',
                'receiver_id' => 7,
                'receiver_role' => 'institute',
                'message' => 'Hello Institute, this is a test message!',
                'attachment_url' => NULL,
                'attachment_type' => NULL,
                'type' => 'text',
                'created_at' => '2025-09-27 17:14:43',
            ),
        ));
        
        
    }
}