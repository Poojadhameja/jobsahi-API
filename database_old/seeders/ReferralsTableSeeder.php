<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReferralsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('referrals')->delete();
        
        \DB::table('referrals')->insert(array (
            0 => 
            array (
                'id' => 1,
                'referrer_id' => 7,
                'referee_email' => 'friend1@gmail.com',
                'job_id' => 1,
                'status' => 'pending',
                'created_at' => '2025-08-26 13:54:01',
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 3,
                'referrer_id' => 10,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-08-26 13:54:01',
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 12,
                'referrer_id' => 5,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-08-29 17:12:57',
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 13,
                'referrer_id' => 6,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-09-01 00:25:23',
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 14,
                'referrer_id' => 7,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-09-01 00:27:15',
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 15,
                'referrer_id' => 9,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-09-03 15:21:38',
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 16,
                'referrer_id' => 11,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-09-03 15:22:27',
                'admin_action' => 'pending',
            ),
            7 => 
            array (
                'id' => 17,
                'referrer_id' => 12,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-09-03 15:30:36',
                'admin_action' => 'pending',
            ),
            8 => 
            array (
                'id' => 18,
                'referrer_id' => 13,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-09-03 15:30:54',
                'admin_action' => 'pending',
            ),
            9 => 
            array (
                'id' => 19,
                'referrer_id' => 14,
                'referee_email' => 'classmate@gmail.com',
                'job_id' => 6,
                'status' => 'pending',
                'created_at' => '2025-09-03 15:40:38',
                'admin_action' => 'pending',
            ),
            10 => 
            array (
                'id' => 20,
                'referrer_id' => 6,
                'referee_email' => 'poojadhameja19@gmail.com',
                'job_id' => 15,
                'status' => 'pending',
                'created_at' => '2025-10-01 12:08:11',
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}