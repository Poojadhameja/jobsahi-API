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
        
        \DB::table('system_alerts')->insert(array (
            0 => 
            array (
                'id' => 1,
                'title' => 'Maintenance Notice',
                'message' => 'System maintenance scheduled for tonight 2:00 AM - 4:00 AM IST',
                'target_role' => 'all',
                'scheduled_at' => '2024-08-25 02:00:00',
                'created_at' => '2025-08-26 14:02:12',
            ),
            1 => 
            array (
                'id' => 2,
                'title' => 'New Feature Launch',
                'message' => 'Check out our new skill assessment feature!',
                'target_role' => 'student',
                'scheduled_at' => '2024-08-20 10:00:00',
                'created_at' => '2025-08-26 14:02:12',
            ),
            2 => 
            array (
                'id' => 3,
                'title' => 'Premium Plan Discount',
                'message' => 'Get 20% off on premium plans this month',
                'target_role' => '',
                'scheduled_at' => '2024-08-15 09:00:00',
                'created_at' => '2025-08-26 14:02:12',
            ),
            3 => 
            array (
                'id' => 4,
                'title' => 'Course Certification Update',
                'message' => 'New certificate templates available for institutes',
                'target_role' => 'institute',
                'scheduled_at' => '2024-08-18 12:00:00',
                'created_at' => '2025-08-26 14:02:12',
            ),
        ));
        
        
    }
}