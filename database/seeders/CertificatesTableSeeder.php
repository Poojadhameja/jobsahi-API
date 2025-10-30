<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CertificatesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('certificates')->delete();
        
        \DB::table('certificates')->insert(array (
            0 => 
            array (
                'id' => 1,
                'student_id' => 1,
                'course_id' => 1,
                'file_url' => '/uploads/institute_certificate/certificate_1761738508.png',
                'issue_date' => '2025-10-29',
                'admin_action' => 'approved',
                'created_at' => '2025-10-29 12:48:28',
                'modified_at' => '2025-10-29 12:48:28',
            ),
        ));
        
        
    }
}