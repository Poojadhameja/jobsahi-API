<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MessageTemplatesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('message_templates')->delete();
        
        \DB::table('message_templates')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Welcome Message',
                'short_description' => 'Welcome new students to the course',
                'message_type' => 'welcome',
                'delivery_type' => 'email',
                'role' => 'institute',
                'subject' => 'Welcome to the Institute!',
                'body' => 'Dear Student, welcome to our institute! We are excited to have you join the course. Please complete registration and attend the orientation.',
                'category' => 'general',
                'is_active' => 1,
                'created_at' => '2025-10-25 00:30:21',
                'updated_at' => '2025-10-25 00:30:21',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Assignment Reminder',
                'short_description' => 'Remind students about pending assignments',
                'message_type' => 'group',
                'delivery_type' => 'email',
                'role' => 'institute',
                'subject' => 'Assignment Reminder',
                'body' => 'This is a reminder to complete your pending assignments before the due date.',
                'category' => 'academic',
                'is_active' => 1,
                'created_at' => '2025-10-25 00:30:32',
                'updated_at' => '2025-10-25 01:30:39',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Exam Schedule',
                'short_description' => 'Updated exam schedule notification',
                'message_type' => 'announcement',
                'delivery_type' => 'email',
                'role' => 'institute',
                'subject' => 'Revised Exam Dates',
                'body' => 'Dear Students, please check your updated exam timetable in the student portal.',
                'category' => 'academic',
                'is_active' => 1,
                'created_at' => '2025-10-25 00:30:42',
                'updated_at' => '2025-10-25 01:30:29',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Holiday Notice',
                'short_description' => 'Inform about institute holidays',
                'message_type' => 'announcement',
                'delivery_type' => 'push',
                'role' => 'institute',
                'subject' => 'Holiday Notice',
                'body' => 'The institute will remain closed on the mentioned date. Enjoy your day off!',
                'category' => 'notice',
                'is_active' => 1,
                'created_at' => '2025-10-25 00:30:50',
                'updated_at' => '2025-10-25 00:30:50',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Fee Reminder',
                'short_description' => 'Remind about fee payment deadlines',
                'message_type' => 'individual',
                'delivery_type' => 'email',
                'role' => 'institute',
                'subject' => 'Fee Payment Reminder',
                'body' => 'Please clear your pending fees before the due date to avoid penalties.',
                'category' => 'finance',
                'is_active' => 1,
                'created_at' => '2025-10-25 00:31:00',
                'updated_at' => '2025-10-25 01:31:13',
            ),
        ));
        
        
    }
}