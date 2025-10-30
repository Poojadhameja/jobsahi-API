<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TransactionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('transactions')->delete();
        
        \DB::table('transactions')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_id' => 5,
                'amount' => '12000.00',
                'method' => 'UPI',
                'purpose' => 'plan',
                'status' => 'success',
                'timestamp' => '2024-08-01 10:00:00',
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 2,
                'user_id' => 11,
                'amount' => '5000.00',
                'method' => 'card',
                'purpose' => 'plan',
                'status' => 'success',
                'timestamp' => '2024-08-15 14:30:00',
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 3,
                'user_id' => 12,
                'amount' => '15000.00',
                'method' => 'UPI',
                'purpose' => 'plan',
                'status' => 'success',
                'timestamp' => '2024-07-01 09:15:00',
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 6,
                'user_id' => 6,
                'amount' => '1500.75',
                'method' => 'wallet',
                'purpose' => 'highlight',
                'status' => 'success',
                'timestamp' => '2025-09-05 12:46:31',
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 7,
                'user_id' => 20,
                'amount' => '1500.75',
                'method' => 'card',
                'purpose' => 'resume_boost',
                'status' => 'failed',
                'timestamp' => '2025-09-05 12:46:53',
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 8,
                'user_id' => 6,
                'amount' => '1500.75',
                'method' => 'UPI',
                'purpose' => 'plan',
                'status' => 'success',
                'timestamp' => '2025-09-05 12:46:59',
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 9,
                'user_id' => 48,
                'amount' => '1500.75',
                'method' => 'wallet',
                'purpose' => 'plan',
                'status' => 'failed',
                'timestamp' => '2025-10-01 16:20:18',
                'admin_action' => 'pending',
            ),
            7 => 
            array (
                'id' => 10,
                'user_id' => 6,
                'amount' => '1500.75',
                'method' => 'card',
                'purpose' => 'plan',
                'status' => 'success',
                'timestamp' => '2025-10-01 18:41:14',
                'admin_action' => 'pending',
            ),
            8 => 
            array (
                'id' => 11,
                'user_id' => 6,
                'amount' => '1500.75',
                'method' => 'card',
                'purpose' => 'plan',
                'status' => 'success',
                'timestamp' => '2025-10-01 18:43:29',
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}