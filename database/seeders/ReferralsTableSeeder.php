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
        
        
        
    }
}