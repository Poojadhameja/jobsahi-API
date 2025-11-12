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
                'name' => 'aarti',
                'email' => 'aarti@gmail.com',
                'phone' => NULL,
                'role' => 'faculty',
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'institute_id' => 1,
                'name' => 'Pooja Dhameja',
                'email' => 'poojadhameja15@gmail.com',
                'phone' => '7484654923',
                'role' => 'faculty',
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}