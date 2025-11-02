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
                'file_url' => '/uploads/certificates/rahul_fswd_cert.pdf',
                'issue_date' => '2024-07-20',
                'admin_action' => 'pending',
                'created_at' => '2025-09-26 14:55:17',
                'modified_at' => '2025-09-26 14:55:17',
            ),
            1 => 
            array (
                'id' => 3,
                'student_id' => 3,
                'course_id' => 3,
                'file_url' => '/uploads/certificates/amit_java_cert.pdf',
                'issue_date' => '2024-06-25',
                'admin_action' => 'pending',
                'created_at' => '2025-09-26 14:55:17',
                'modified_at' => '2025-09-26 14:55:17',
            ),
            2 => 
            array (
                'id' => 4,
                'student_id' => 5,
                'course_id' => 5,
                'file_url' => '/uploads/certificates/arjun_rn_cert.pdf',
                'issue_date' => '2024-06-15',
                'admin_action' => 'pending',
                'created_at' => '2025-09-26 14:55:17',
                'modified_at' => '2025-09-26 14:55:17',
            ),
            3 => 
            array (
                'id' => 6,
                'student_id' => 1,
                'course_id' => 2,
                'file_url' => 'http://example.com/certificates/certificate1.pdf',
                'issue_date' => '2025-09-04',
                'admin_action' => 'approved',
                'created_at' => '2025-09-26 14:55:17',
                'modified_at' => '2025-09-26 14:55:17',
            ),
            4 => 
            array (
                'id' => 7,
                'student_id' => 3,
                'course_id' => 2,
                'file_url' => 'http://example.com/certificates/certificate1.pdf',
                'issue_date' => '2025-09-04',
                'admin_action' => 'approved',
                'created_at' => '2025-09-26 14:55:17',
                'modified_at' => '2025-09-26 14:55:17',
            ),
            5 => 
            array (
                'id' => 9,
                'student_id' => 7,
                'course_id' => 10,
                'file_url' => 'http://example.com/certificates/FSWD.pdf',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2025-09-26 14:55:17',
                'modified_at' => '2025-09-26 14:55:17',
            ),
            6 => 
            array (
                'id' => 10,
                'student_id' => 7,
                'course_id' => 2,
                'file_url' => 'http://example.com/certificates/FSWD.pdf',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2025-09-26 14:55:17',
                'modified_at' => '2025-09-26 14:55:17',
            ),
            7 => 
            array (
                'id' => 11,
                'student_id' => 7,
                'course_id' => 3,
                'file_url' => 'http://example.com/certificates/FSWD.pdf',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2025-09-26 15:18:36',
                'modified_at' => '2025-09-26 15:18:36',
            ),
            8 => 
            array (
                'id' => 12,
                'student_id' => 7,
                'course_id' => 5,
                'file_url' => 'http://example.com/certificates/FSWD.pdf',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2025-09-26 15:19:57',
                'modified_at' => '2025-09-26 15:19:57',
            ),
            9 => 
            array (
                'id' => 13,
                'student_id' => 7,
                'course_id' => 9,
                'file_url' => 'http://example.com/certificates/FSWD.pdf',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2025-09-26 15:40:57',
                'modified_at' => '2025-09-26 15:40:57',
            ),
            10 => 
            array (
                'id' => 14,
                'student_id' => 6,
                'course_id' => 10,
                'file_url' => 'http://example.com/certificates/FSWD.pdf',
                'issue_date' => '2025-02-04',
                'admin_action' => 'approved',
                'created_at' => '2024-09-01 00:00:00',
                'modified_at' => '2025-09-26 12:25:02',
            ),
        ));
        
        
    }
}