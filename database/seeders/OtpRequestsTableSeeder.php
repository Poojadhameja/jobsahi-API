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
        
        
        
    }
}