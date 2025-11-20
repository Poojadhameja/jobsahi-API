<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CertificateTemplatesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('certificate_templates')->delete();
        
        \DB::table('certificate_templates')->insert(array (
            0 => 
            array (
                'id' => 1,
                'institute_id' => 1,
                'template_name' => 'Updated Certificate',
                'logo' => '/uploads/institute_certificate_templates/logo_url_1761750317.png',
                'seal' => '/uploads/institute_certificate_templates/seal_url_1761750317.png',
                'signature' => '/uploads/institute_certificate_templates/signature_url_1761750317.png',
                'description' => 'Updated Certificate Template - Powered by JobSahi',
                'is_active' => 1,
                'created_at' => '2025-10-29 21:01:54',
                'modified_at' => '2025-10-29 21:01:51',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'institute_id' => 1,
                'template_name' => 'Default Certificate',
                'logo' => '/uploads/institute_certificate_templates/logo_url_1761748289.png',
                'seal' => '/uploads/institute_certificate_templates/seal_url_1761748290.png',
                'signature' => '/uploads/institute_certificate_templates/signature_url_1761748290.png',
                'description' => 'Certificate of Completion - Powered by JobSahi',
                'is_active' => 1,
                'created_at' => '2025-10-29 15:31:29',
                'modified_at' => '2025-10-29 15:31:29',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}