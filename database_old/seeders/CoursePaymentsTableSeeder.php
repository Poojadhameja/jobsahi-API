<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CoursePaymentsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('course_payments')->delete();
        
        \DB::table('course_payments')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 1,
                'course_id' => 1,
                'enrollment_id' => 1,
                'amount' => '45000.00',
                'currency' => 'INR',
                'status' => 'paid',
                'method' => 'UPI',
                'transaction_ref' => 'TXN_FSW_001',
                'gateway_response_json' => NULL,
                'paid_at' => '2024-01-15 10:30:00',
                'created_at' => '2024-01-15 10:00:00',
                'modified_at' => '2025-08-26 13:32:09',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 3,
                'student_id' => 3,
                'course_id' => 3,
                'enrollment_id' => 3,
                'amount' => '40000.00',
                'currency' => 'INR',
                'status' => 'paid',
                'method' => 'netbanking',
                'transaction_ref' => 'TXN_JAVA_003',
                'gateway_response_json' => NULL,
                'paid_at' => '2024-01-20 12:30:00',
                'created_at' => '2024-01-20 12:00:00',
                'modified_at' => '2025-08-26 13:32:09',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 4,
                'student_id' => 4,
                'course_id' => 1,
                'enrollment_id' => 4,
                'amount' => '45000.00',
                'currency' => 'INR',
                'status' => 'paid',
                'method' => 'UPI',
                'transaction_ref' => 'TXN_FSW_004',
                'gateway_response_json' => NULL,
                'paid_at' => '2024-03-01 13:30:00',
                'created_at' => '2024-03-01 13:00:00',
                'modified_at' => '2025-08-26 13:32:09',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 5,
                'student_id' => 5,
                'course_id' => 5,
                'enrollment_id' => 5,
                'amount' => '38000.00',
                'currency' => 'INR',
                'status' => 'paid',
                'method' => 'card',
                'transaction_ref' => 'TXN_RN_005',
                'gateway_response_json' => NULL,
                'paid_at' => '2024-02-10 14:30:00',
                'created_at' => '2024-02-10 14:00:00',
                'modified_at' => '2025-08-26 13:32:09',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 6,
                'student_id' => 6,
                'course_id' => 2,
                'enrollment_id' => 6,
                'amount' => '35000.00',
                'currency' => 'INR',
                'status' => 'pending',
                'method' => 'UPI',
                'transaction_ref' => 'TXN_DS_006',
                'gateway_response_json' => NULL,
                'paid_at' => '2024-04-24 13:52:58',
                'created_at' => '2024-02-01 15:00:00',
                'modified_at' => '2025-08-29 13:53:23',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}