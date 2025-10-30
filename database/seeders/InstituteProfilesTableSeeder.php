<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InstituteProfilesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('institute_profiles')->delete();
        
        \DB::table('institute_profiles')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_id' => 3,
                'institute_name' => NULL,
                'institute_type' => NULL,
                'website' => NULL,
                'description' => NULL,
                'address' => NULL,
                'city' => NULL,
                'state' => NULL,
                'country' => NULL,
                'postal_code' => NULL,
                'contact_person' => NULL,
                'contact_designation' => NULL,
                'accreditation' => NULL,
                'established_year' => NULL,
                'location' => NULL,
                'courses_offered' => NULL,
                'created_at' => '2025-10-29 11:32:26',
                'modified_at' => '2025-10-29 11:32:26',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}