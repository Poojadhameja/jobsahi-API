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
                'course_id' => 8,
                'file_url' => '/uploads/institute_certificate/certificate_1761890913.png',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2025-10-31 07:08:33',
                'modified_at' => '2025-10-31 07:08:33',
            ),
            1 => 
            array (
                'id' => 2,
                'student_id' => 1,
                'course_id' => 1,
                'file_url' => '/uploads/institute_certificate/certificate_1761890964.png',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2025-10-31 07:09:24',
                'modified_at' => '2025-10-31 07:09:24',
            ),
        ));
        
        
    }
}