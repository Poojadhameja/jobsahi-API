<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_name' => 'Brightorial Admin',
                'email' => 'brightorial.ad@gmail.com',
                'password' => '$2y$10$tbdAFECObpDFdla5v996zOfoGvM9qZeKuTRud0fvpkaOyb/EXX7uy',
                'role' => 'admin',
                'phone_number' => '9876543219',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-29 11:32:17',
                'created_at' => '2025-10-29 11:32:17',
                'updated_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'user_name' => 'Brightorial Recruiter',
                'email' => 'brightorial.rc@gmail.com',
                'password' => '$2y$10$etpfHeSb5hNP2V9xil.QIOnvw0o6OxJ8ulWRSLl5x5aJxxKDJYY3W',
                'role' => 'recruiter',
                'phone_number' => '9145236789',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-29 11:32:22',
                'created_at' => '2025-10-29 11:32:22',
                'updated_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'user_name' => 'Brightorial Institute',
                'email' => 'brightorial.in@gmail.com',
                'password' => '$2y$10$XZzrLELljhtJnN8JoJLLGenITuShF7FAof8JL2//jX/NC0s0/dB82',
                'role' => 'institute',
                'phone_number' => '9847423615',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-29 11:32:26',
                'created_at' => '2025-10-29 11:32:26',
                'updated_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'user_name' => 'Brightorial Student',
                'email' => 'brightorial.sc@gmail.com',
                'password' => '$2y$10$1hWjEZGFSefYA8fs1dYcCOEBBLeXW8X0GSeKkEBJPlH/lkFRo5OvW',
                'role' => 'student',
                'phone_number' => '9874561230',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-29 11:32:30',
                'created_at' => '2025-10-29 11:32:30',
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}