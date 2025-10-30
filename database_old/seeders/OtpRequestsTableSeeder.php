<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OtpRequestsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('otp_requests')->delete();
        
        \DB::table('otp_requests')->insert(array (
            0 => 
            array (
                'id' => 127,
                'user_id' => 51,
                'otp_code' => '026297',
                'purpose' => 'forgot_password',
                'is_used' => 1,
                'created_at' => '2025-10-05 18:16:22',
                'expires_at' => '2025-10-05 12:51:22',
            ),
        ));
        
        
    }
}