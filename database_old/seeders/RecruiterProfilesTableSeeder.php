<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RecruiterProfilesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('recruiter_profiles')->delete();
        
        \DB::table('recruiter_profiles')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_id' => 3,
                'company_name' => 'TechCorp Solutions',
                'company_logo' => '/uploads/logos/techcorp_logo.png',
                'industry' => 'Technology',
                'website' => 'https://techcorp.com',
                'location' => 'Mumbai, India',
                'created_at' => '2024-05-15 10:00:00',
                'modified_at' => '2025-09-25 17:31:06',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'user_id' => 16,
                'company_name' => 'InnovateLabs',
                'company_logo' => '/uploads/logos/innovate_logo.png',
                'industry' => 'AI & Machine Learning',
                'website' => 'https://innovatelabs.com',
                'location' => 'Bangalore, India',
                'created_at' => '2024-05-20 11:00:00',
                'modified_at' => '2025-09-25 17:31:12',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            2 => 
            array (
                'id' => 3,
                'user_id' => 17,
                'company_name' => 'DataFlow Systems',
                'company_logo' => '/uploads/logos/dataflow_logo.png',
                'industry' => 'Data Analytics',
                'website' => 'https://dataflow.com',
                'location' => 'Pune, India',
                'created_at' => '2024-05-25 12:00:00',
                'modified_at' => '2025-09-25 17:31:18',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            3 => 
            array (
                'id' => 4,
                'user_id' => 19,
                'company_name' => 'CloudTech Innovations',
                'company_logo' => '/uploads/logos/cloudtech_logo.png',
                'industry' => 'Cloud Computing',
                'website' => 'https://cloudtech.com',
                'location' => 'Hyderabad, India',
                'created_at' => '2024-06-01 13:00:00',
                'modified_at' => '2025-09-25 17:31:24',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            4 => 
            array (
                'id' => 5,
                'user_id' => 49,
                'company_name' => 'DevOps Masters',
                'company_logo' => '/uploads/logos/devops_logo.png',
                'industry' => 'DevOps & Automation',
                'website' => 'https://devopsmasters.com',
                'location' => 'Chennai, India',
                'created_at' => '2024-06-05 14:00:00',
                'modified_at' => '2025-09-25 17:31:30',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            5 => 
            array (
                'id' => 6,
                'user_id' => 52,
                'company_name' => 'FinTech Solutions',
                'company_logo' => '/uploads/logos/fintech_logo.png',
                'industry' => 'Financial Technology',
                'website' => 'https://fintechsolutions.com',
                'location' => 'Delhi, India',
                'created_at' => '2024-06-10 15:00:00',
                'modified_at' => '2025-09-25 17:31:36',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            6 => 
            array (
                'id' => 7,
                'user_id' => 53,
                'company_name' => 'MobileFirst Apps',
                'company_logo' => '/uploads/logos/mobilefirst_logo.png',
                'industry' => 'Mobile Development',
                'website' => 'https://mobilefirst.com',
                'location' => 'Gurgaon, India',
                'created_at' => '2024-06-15 16:00:00',
                'modified_at' => '2025-09-25 17:31:42',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            7 => 
            array (
                'id' => 8,
                'user_id' => 54,
                'company_name' => 'WebCraft Studios',
                'company_logo' => '/uploads/logos/webcraft_logo.png',
                'industry' => 'Web Development',
                'website' => 'https://webcraft.com',
                'location' => 'Kolkata, India',
                'created_at' => '2024-06-20 17:00:00',
                'modified_at' => '2025-09-25 17:31:48',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            8 => 
            array (
                'id' => 9,
                'user_id' => 55,
                'company_name' => 'AI Dynamics',
                'company_logo' => '/uploads/logos/aidynamics_logo.png',
                'industry' => 'Artificial Intelligence',
                'website' => 'https://aidynamics.com',
                'location' => 'Noida, India',
                'created_at' => '2024-06-25 18:00:00',
                'modified_at' => '2025-09-25 17:31:54',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            9 => 
            array (
                'id' => 10,
                'user_id' => 56,
                'company_name' => 'BlockChain Ventures',
                'company_logo' => '/uploads/logos/blockchain_logo.png',
                'industry' => 'Blockchain Technology',
                'website' => 'https://blockchainventures.com',
                'location' => 'Ahmedabad, India',
                'created_at' => '2024-07-01 19:00:00',
                'modified_at' => '2025-09-25 17:32:00',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            10 => 
            array (
                'id' => 11,
                'user_id' => 57,
                'company_name' => 'CyberSec Pro',
                'company_logo' => '/uploads/logos/cybersec_logo.png',
                'industry' => 'Cybersecurity',
                'website' => 'https://cybersecpro.com',
                'location' => 'Jaipur, India',
                'created_at' => '2024-07-05 20:00:00',
                'modified_at' => '2025-09-25 17:32:06',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            11 => 
            array (
                'id' => 12,
                'user_id' => 58,
                'company_name' => 'GameDev Studios',
                'company_logo' => '/uploads/logos/gamedev_logo.png',
                'industry' => 'Game Development',
                'website' => 'https://gamedevstudios.com',
                'location' => 'Indore, India',
                'created_at' => '2024-07-10 21:00:00',
                'modified_at' => '2025-09-25 17:32:12',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            12 => 
            array (
                'id' => 13,
                'user_id' => 59,
                'company_name' => 'IoT Solutions',
                'company_logo' => '/uploads/logos/iot_logo.png',
                'industry' => 'Internet of Things',
                'website' => 'https://iotsolutions.com',
                'location' => 'Coimbatore, India',
                'created_at' => '2024-07-15 22:00:00',
                'modified_at' => '2025-09-25 17:32:18',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            13 => 
            array (
                'id' => 14,
                'user_id' => 60,
                'company_name' => 'AR/VR Labs',
                'company_logo' => '/uploads/logos/arvr_logo.png',
                'industry' => 'Augmented Reality',
                'website' => 'https://arvrlabs.com',
                'location' => 'Kochi, India',
                'created_at' => '2024-07-20 23:00:00',
                'modified_at' => '2025-09-25 17:32:24',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            14 => 
            array (
                'id' => 15,
                'user_id' => 61,
                'company_name' => 'EduTech Innovations',
                'company_logo' => '/uploads/logos/edutech_logo.png',
                'industry' => 'Educational Technology',
                'website' => 'https://edutechinnovations.com',
                'location' => 'Chandigarh, India',
                'created_at' => '2024-07-25 00:00:00',
                'modified_at' => '2025-09-25 17:32:30',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            15 => 
            array (
                'id' => 16,
                'user_id' => 62,
                'company_name' => 'HealthTech Systems',
                'company_logo' => '/uploads/logos/healthtech_logo.png',
                'industry' => 'Healthcare Technology',
                'website' => 'https://healthtechsystems.com',
                'location' => 'Bhubaneswar, India',
                'created_at' => '2024-07-30 01:00:00',
                'modified_at' => '2025-09-25 17:32:36',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            16 => 
            array (
                'id' => 17,
                'user_id' => 63,
                'company_name' => 'AgriTech Solutions',
                'company_logo' => '/uploads/logos/agritech_logo.png',
                'industry' => 'Agricultural Technology',
                'website' => 'https://agritechsolutions.com',
                'location' => 'Nagpur, India',
                'created_at' => '2024-08-01 02:00:00',
                'modified_at' => '2025-09-25 17:32:42',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            17 => 
            array (
                'id' => 18,
                'user_id' => 64,
                'company_name' => 'RetailTech Hub',
                'company_logo' => '/uploads/logos/retailtech_logo.png',
                'industry' => 'Retail Technology',
                'website' => 'https://retailtechhub.com',
                'location' => 'Vadodara, India',
                'created_at' => '2024-08-05 03:00:00',
                'modified_at' => '2025-09-25 17:32:48',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            18 => 
            array (
                'id' => 19,
                'user_id' => 65,
                'company_name' => 'LogisticsTech',
                'company_logo' => '/uploads/logos/logisticstech_logo.png',
                'industry' => 'Logistics Technology',
                'website' => 'https://logisticstech.com',
                'location' => 'Surat, India',
                'created_at' => '2024-08-10 04:00:00',
                'modified_at' => '2025-09-25 17:32:54',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            19 => 
            array (
                'id' => 20,
                'user_id' => 66,
                'company_name' => 'EnergyTech Corp',
                'company_logo' => '/uploads/logos/energytech_logo.png',
                'industry' => 'Energy Technology',
                'website' => 'https://energytechcorp.com',
                'location' => 'Visakhapatnam, India',
                'created_at' => '2024-08-15 05:00:00',
                'modified_at' => '2025-09-25 17:33:00',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}