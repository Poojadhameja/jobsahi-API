<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NotificationsTemplatesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('notifications_templates')->delete();
        
        \DB::table('notifications_templates')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Welcome Email',
                'type' => 'email',
                'subject' => 'Welcome to JobSahi!',
                'body' => 'Welcome to JobSahi! We are excited to have you join our community.',
                'created_at' => '2025-08-26 14:36:09',
                'role' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Job Application SMS',
                'type' => 'sms',
                'subject' => '',
                'body' => 'Your job application has been submitted successfully. We will keep you updated.',
                'created_at' => '2025-08-26 14:36:09',
                'role' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Interview Reminder',
                'type' => 'email',
                'subject' => 'Interview Reminder',
                'body' => 'This is a reminder about your upcoming interview scheduled for 2024-08-20 at 14:30:00.',
                'created_at' => '2025-08-26 14:36:09',
                'role' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Course Enrollment',
                'type' => 'push',
                'subject' => '',
                'body' => 'You have successfully enrolled in Full Stack Development. Classes start on 2024-09-01.',
                'created_at' => '2025-08-26 14:36:09',
                'role' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Updated Welcome Email',
                'type' => 'email',
                'subject' => 'Your account has been updated',
                'body' => 'Hello {{name}}, your account was successfully updated.',
                'created_at' => '2025-09-05 01:25:38',
                'role' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Welcome Email',
                'type' => 'email',
                'subject' => 'Welcome to JOBSAHI!',
                'body' => 'Hello {{user}}, thank you for joining JOBSAHI. We\'re glad to have you!',
                'created_at' => '2025-09-05 01:30:08',
                'role' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Updated Welcome to our College',
                'type' => 'email',
                'subject' => 'Your account has been updated',
                'body' => 'Hello {{name}}, your account was successfully updated.',
                'created_at' => '2025-09-05 01:48:08',
                'role' => NULL,
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'Updated Welcome to our placement cell',
                'type' => 'email',
                'subject' => 'Your account has been updated',
                'body' => 'Hello {{name}}, your account was successfully updated.',
                'created_at' => '2025-09-05 01:49:11',
                'role' => NULL,
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'Welcome Email',
                'type' => 'email',
                'subject' => 'Welcome to our platform',
                'body' => 'Hello {{name}}, thank you for joining us!',
                'created_at' => '2025-09-05 01:51:27',
                'role' => NULL,
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'Job Application Reminder',
                'type' => 'email',
                'subject' => 'Reminder: Complete Your Application',
                'body' => 'Dear user, please complete your job application before the deadline.',
                'created_at' => '2025-09-30 16:58:21',
                'role' => NULL,
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'Job Application Reminder',
                'type' => 'email',
                'subject' => 'Reminder: Complete Your Application',
                'body' => 'Dear user, please complete your job application before the deadline.',
                'created_at' => '2025-09-30 17:11:31',
                'role' => NULL,
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'Job Application Reminder',
                'type' => 'email',
                'subject' => 'Reminder: Complete Your Application',
                'body' => 'Dear user, please complete your job application before the deadline.',
                'created_at' => '2025-10-01 17:35:45',
                'role' => NULL,
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'Job Application Reminder',
                'type' => 'email',
                'subject' => 'Reminder: Complete Your Application',
                'body' => 'Dear user, please complete your job application before the deadline.',
                'created_at' => '2025-10-01 21:43:07',
                'role' => 'institute',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => 'Job Application Reminder',
                'type' => 'email',
                'subject' => 'Reminder: Complete Your Application',
                'body' => 'Dear user, please complete your job application before the deadline.',
                'created_at' => '2025-10-01 21:49:01',
                'role' => 'recruiter',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => 'Job Application Reminder',
                'type' => 'email',
                'subject' => 'Reminder: Complete Your Application',
                'body' => 'Dear user, please complete your job application before the deadline.',
                'created_at' => '2025-10-01 22:02:10',
                'role' => 'recruiter',
            ),
            15 => 
            array (
                'id' => 16,
                'name' => 'Job Application Reminder',
                'type' => 'email',
                'subject' => 'Reminder: Complete Your Application',
                'body' => 'Dear user, please complete your job application before the deadline.',
                'created_at' => '2025-10-01 22:16:52',
                'role' => 'recruiter',
            ),
        ));
        
        
    }
}