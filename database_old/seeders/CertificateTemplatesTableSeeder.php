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
                'template_name' => 'Tech Institute Delhi Certificate',
                'logo_url' => '/uploads/templates/tid_logo.png',
                'seal_url' => '/uploads/templates/tid_seal.png',
                'signature_url' => '/uploads/templates/director_signature.png',
                'header_text' => 'TECH INSTITUTE DELHI',
                'footer_text' => 'Authorized by Tech Institute Delhi',
                'background_image_url' => '/uploads/templates/cert_bg_1.jpg',
                'is_active' => 1,
                'created_at' => '2025-08-26 13:32:59',
                'modified_at' => '2025-08-26 13:32:59',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 2,
                'institute_id' => 2,
                'template_name' => 'NIIT Mumbai Certificate',
                'logo_url' => '/uploads/templates/niit_logo.png',
                'seal_url' => '/uploads/templates/niit_seal.png',
                'signature_url' => '/uploads/templates/niit_signature.png',
                'header_text' => 'NIIT MUMBAI',
                'footer_text' => 'Certified by NIIT Mumbai',
                'background_image_url' => '/uploads/templates/cert_bg_2.jpg',
                'is_active' => 1,
                'created_at' => '2025-08-26 13:32:59',
                'modified_at' => '2025-08-26 13:32:59',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 3,
                'institute_id' => 3,
                'template_name' => 'Code Academy Bangalore Certificate',
                'logo_url' => '/uploads/templates/cab_logo.png',
                'seal_url' => '/uploads/templates/cab_seal.png',
                'signature_url' => '/uploads/templates/cab_signature.png',
                'header_text' => 'CODE ACADEMY BANGALORE',
                'footer_text' => 'Certified by Code Academy Bangalore',
                'background_image_url' => '/uploads/templates/cert_bg_3.jpg',
                'is_active' => 1,
                'created_at' => '2025-08-26 13:32:59',
                'modified_at' => '2025-09-26 16:30:55',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            3 => 
            array (
                'id' => 4,
                'institute_id' => 1,
                'template_name' => 'Certificate of Excellence',
                'logo_url' => 'https://example.com/logo.png',
                'seal_url' => 'https://example.com/seal.png',
                'signature_url' => 'https://example.com/signature.png',
                'header_text' => 'This certificate is awarded to',
                'footer_text' => 'Congratulations!',
                'background_image_url' => 'https://example.com/bg.png',
                'is_active' => 1,
                'created_at' => '2025-09-04 17:18:55',
                'modified_at' => '2025-09-04 17:18:55',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            4 => 
            array (
                'id' => 5,
                'institute_id' => 1,
                'template_name' => 'Certificate of Excellence',
                'logo_url' => 'https://example.com/logo.png',
                'seal_url' => 'https://example.com/seal.png',
                'signature_url' => 'https://example.com/signature.png',
                'header_text' => 'This certificate is awarded to',
                'footer_text' => 'Congratulations!',
                'background_image_url' => 'https://example.com/bg.png',
                'is_active' => 1,
                'created_at' => '2025-09-04 17:19:13',
                'modified_at' => '2025-09-04 17:19:13',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            5 => 
            array (
                'id' => 6,
                'institute_id' => 4,
                'template_name' => 'Default Certificate',
                'logo_url' => 'http://example.com/logo.png',
                'seal_url' => 'http://example.com/seal.png',
                'signature_url' => 'http://example.com/signature.png',
                'header_text' => 'Certificate of Excellence',
                'footer_text' => 'Issued by ABC Institute',
                'background_image_url' => 'http://example.com/background.png',
                'is_active' => 1,
                'created_at' => '2025-09-26 16:22:27',
                'modified_at' => '2025-09-26 16:22:27',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}