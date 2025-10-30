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
                'user_id' => 2,
                'institute_name' => 'Himanshu Tech Institute',
                'institute_type' => 'Private University',
                'website' => 'https://www.himanshutech.edu',
                'description' => 'Leading institute specializing in technology and software development education with modern curriculum.',
                'address' => '123 Tech Park, Sector 5',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '400001',
                'contact_person' => 'Dr. Himanshu Kumar',
                'contact_designation' => 'Director of Admissions',
                'accreditation' => 'NAAC A+ Accredited, AICTE Approved',
                'established_year' => 2015,
                'location' => 'Mumbai, India',
                'courses_offered' => 'Full Stack Development, Data Science, Machine Learning, Python Programming, Java Development, Cloud Computing',
                'created_at' => '2024-01-15 09:00:00',
                'modified_at' => '2025-09-30 00:59:15',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            1 => 
            array (
                'id' => 2,
                'user_id' => 13,
                'institute_name' => 'Tech Institute of Delhi',
                'institute_type' => 'Private University',
                'website' => 'https://www.techinstitutedelhi.edu',
                'description' => 'Leading institute specializing in technology and software development education.',
                'address' => '123 Connaught Place, Block A',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'country' => 'India',
                'postal_code' => '110001',
                'contact_person' => 'Dr. Rajesh Kumar',
                'contact_designation' => 'Director of Admissions',
                'accreditation' => 'NAAC A+ Accredited, AICTE Approved',
                'established_year' => 2010,
                'location' => 'Delhi, India',
                'courses_offered' => 'Full Stack Development, Data Science, Machine Learning, Python Programming',
                'created_at' => '2024-01-15 09:00:00',
                'modified_at' => '2025-09-30 00:59:15',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            2 => 
            array (
                'id' => 3,
                'user_id' => 14,
                'institute_name' => 'Mumbai Institute of Technology',
                'institute_type' => 'Deemed University',
                'website' => 'https://www.mitbombay.edu',
                'description' => 'Premier institute for Java development and Spring Boot training.',
                'address' => '456 Andheri West, Main Road',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '400058',
                'contact_person' => 'Prof. Priya Sharma',
                'contact_designation' => 'Head of Department',
                'accreditation' => 'UGC Approved, NAAC A Accredited',
                'established_year' => 2008,
                'location' => 'Mumbai, India',
                'courses_offered' => 'Java Development, Spring Boot, Microservices, Cloud Computing',
                'created_at' => '2024-01-20 10:00:00',
                'modified_at' => '2025-09-30 00:59:15',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            3 => 
            array (
                'id' => 4,
                'user_id' => 15,
                'institute_name' => 'Bangalore Tech Academy',
                'institute_type' => 'Private College',
                'website' => 'https://www.bangaloretech.edu',
                'description' => 'Specialized training in Full Stack and Mobile App Development.',
                'address' => '789 MG Road, Sector 5',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'country' => 'India',
                'postal_code' => '560001',
                'contact_person' => 'Mr. Arun Patel',
                'contact_designation' => 'Academic Coordinator',
                'accreditation' => 'AICTE Approved, ISO 9001 Certified',
                'established_year' => 2012,
                'location' => 'Bangalore, India',
                'courses_offered' => 'React Development, Node.js, Mobile App Development, DevOps',
                'created_at' => '2024-01-25 11:00:00',
                'modified_at' => '2025-09-30 00:59:15',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
            4 => 
            array (
                'id' => 5,
                'user_id' => 50,
                'institute_name' => 'Mumbai Coding Institute',
                'institute_type' => 'Training Center',
                'website' => 'https://www.mumbaicodingacademy.com',
                'description' => 'Professional training center for Java, PHP, and React development.',
                'address' => '321 Bandra East, Commercial Complex',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '400051',
                'contact_person' => 'Ms. Neha Reddy',
                'contact_designation' => 'Training Manager',
                'accreditation' => 'NSDC Affiliated, Skill India Partner',
                'established_year' => 2015,
                'location' => 'Mumbai',
                'courses_offered' => 'Java, PHP, React',
                'created_at' => '2025-09-25 15:30:31',
                'modified_at' => '2025-09-30 00:59:15',
                'deleted_at' => NULL,
                'admin_action' => 'approved',
            ),
        ));
        
        
    }
}