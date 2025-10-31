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
                'company_name' => '',
                'company_logo' => NULL,
                'industry' => NULL,
                'website' => NULL,
                'location' => NULL,
                'created_at' => '2025-10-29 11:32:22',
                'modified_at' => '2025-10-30 02:17:35',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}