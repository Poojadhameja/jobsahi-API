<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SubscriptionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('subscriptions')->delete();
        
        \DB::table('subscriptions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_id' => 5,
                'plan_name' => 'Premium Employer',
                'type' => 'employer',
                'start_date' => '2024-08-01',
                'expiry_date' => '2024-08-31',
                'credits_remaining' => 18,
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 2,
                'user_id' => 11,
                'plan_name' => 'Basic Employer',
                'type' => 'employer',
                'start_date' => '2024-08-15',
                'expiry_date' => '2024-09-14',
                'credits_remaining' => 4,
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 3,
                'user_id' => 12,
                'plan_name' => 'Premium Institute',
                'type' => 'institute',
                'start_date' => '2024-07-01',
                'expiry_date' => '2024-07-31',
                'credits_remaining' => 450,
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 4,
                'user_id' => 13,
                'plan_name' => 'Basic Institute',
                'type' => 'institute',
                'start_date' => '2024-08-10',
                'expiry_date' => '2024-09-09',
                'credits_remaining' => 95,
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}