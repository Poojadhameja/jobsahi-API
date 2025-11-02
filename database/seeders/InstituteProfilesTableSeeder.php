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
                'institute_name' => 'Satpuda ITI College',
                'institute_type' => 'Private',
                'website' => 'https://satpudaiticollege.com',
                'description' => 'Empowering students with hands-on skills.',
                'address' => 'Lalburra - Balaghat Road, MP 481001',
                'city' => 'Balaghat',
                'state' => 'Madhya Pradesh',
                'country' => 'India',
                'postal_code' => '481001',
                'contact_person' => 'Mr. Rajeev Sharma',
                'contact_designation' => 'Director',
                'accreditation' => 'NCVT Approved',
                'established_year' => 2004,
                'location' => 'Balaghat, MP',
                'courses_offered' => 'Electrician, Fitter, COPA',
                'created_at' => '2025-10-29 11:32:26',
                'modified_at' => '2025-10-31 12:02:54',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}