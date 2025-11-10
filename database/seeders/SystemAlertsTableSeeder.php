<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SystemAlertsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('system_alerts')->delete();
        
        
        
    }
}