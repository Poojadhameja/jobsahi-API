<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FacultyUsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('faculty_users')->delete();
        
        \DB::table('faculty_users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'institute_id' => 1,
                'name' => 'Dr. Rajesh Kumar',
                'email' => 'rajesh.kumar@techinstituteDelhi.com',
                'phone' => '9876543221',
                'role' => 'admin',
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'institute_id' => 1,
                'name' => 'Sunita Sharma',
                'email' => 'sunita.sharma@techinstituteDelhi.com',
                'phone' => '9876543222',
                'role' => 'faculty',
                'admin_action' => 'approved',
            ),
            2 => 
            array (
                'id' => 3,
                'institute_id' => 2,
                'name' => 'Prof. Vikram Singh',
                'email' => 'vikram.singh@niitmumbai.com',
                'phone' => '9876543223',
                'role' => 'admin',
                'admin_action' => 'approved',
            ),
            3 => 
            array (
                'id' => 4,
                'institute_id' => 2,
                'name' => 'Anjali Verma',
                'email' => 'anjali.verma@niitmumbai.com',
                'phone' => '9876543224',
                'role' => 'faculty',
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 5,
                'institute_id' => 3,
                'name' => 'Ravi Krishnan',
                'email' => 'ravi.krishnan@codeacademybangalore.com',
                'phone' => '9876543225',
                'role' => 'admin',
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 6,
                'institute_id' => 3,
                'name' => 'Meera Nair',
                'email' => 'meera.nair@codeacademybangalore.com',
                'phone' => '9876543226',
                'role' => 'faculty',
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 7,
                'institute_id' => 4,
                'name' => 'Pooja Dhameja',
                'email' => 'poojadhameja15@gmail.com',
                'phone' => '7484654923',
                'role' => 'faculty',
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}