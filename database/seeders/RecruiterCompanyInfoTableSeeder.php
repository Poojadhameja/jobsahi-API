<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RecruiterCompanyInfoTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('recruiter_company_info')->delete();
        
        \DB::table('recruiter_company_info')->insert(array (
            0 => 
            array (
                'id' => 1,
                'job_id' => 2,
                'recruiter_id' => 1,
                'person_name' => 'Rahul Sharma',
                'phone' => '9876543210',
                'additional_contact' => 'hr@buildcorp.com',
                'created_at' => '2025-10-30 02:18:28',
                'updated_at' => '2025-10-30 02:18:28',
            ),
            1 => 
            array (
                'id' => 2,
                'job_id' => 1,
                'recruiter_id' => 1,
                'person_name' => 'Rahul Sharma',
                'phone' => '9876501234',
                'additional_contact' => 'rahul@techcorp.com',
                'created_at' => '2025-10-30 02:22:19',
                'updated_at' => '2025-10-30 02:22:19',
            ),
        ));
        
        
    }
}