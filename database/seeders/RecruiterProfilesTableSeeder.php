<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RecruiterProfilesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('recruiter_profiles')->delete();
        
        \DB::table('recruiter_profiles')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_id' => 2,
                'company_name' => 'Brightorial Tech Pvt Ltd',
                'company_logo' => '/uploads/recruiter_logo/logo_1761927989_8814.png',
                'industry' => 'IT',
            'website' => '[https://brightorial.com](https://brightorial.com)',
                'location' => 'Indore',
                'created_at' => '2025-10-29 11:32:22',
                'modified_at' => '2025-10-31 21:56:29',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}