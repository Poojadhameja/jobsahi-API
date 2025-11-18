<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StudentProfilesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('student_profiles')->delete();
        
        \DB::table('student_profiles')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_id' => 4,
                'skills' => 'PHP, SQL, JavaScript, HTML, CSS, React, Node.js',
            'education' => 'Bachelor of Technology in Computer Science (B.Tech)',
                'resume' => '/api/uploads/resume/resume_1761761672.pdf',
                'certificates' => '/uploads/student_certificate/certificate_1761762581.png',
                'socials' => '[{"title":"Portfolio","profile_url":"https://portfolio.example.com/student5"},{"title":"LinkedIn","profile_url":"https://linkedin.com/in/student5"}]',
                'bio' => 'A passionate software developer focused on building scalable backend systems and modern web applications. Skilled in PHP, React, and SQL.',
                'dob' => '2000-05-15',
                'gender' => 'male',
                'job_type' => 'full_time',
                'trade' => 'IT',
                'location' => 'Indore, Madhya Pradesh',
                'experience' => '[{"company":"TechNova Pvt Ltd","role":"Backend Developer","duration":"Jan 2023 - May 2024","description":"Developed and maintained PHP-based REST APIs for client applications."},{"company":"Brightorial Tech Solutions","role":"Intern","duration":"Aug 2022 - Dec 2022","description":"Assisted in developing a React-based admin panel with MySQL backend."}]',
                'projects' => '[{"name":"JobSahi Platform","link":"https:\\/\\/jobsahi.in"},{"name":"New Project","link":"https:\\/\\/newproject.com"}]',
                'languages' => 'English, Hindi, Gujarati',
                'aadhar_number' => '9876-5432-8985',
                'graduation_year' => 2023,
                'cgpa' => '8.70',
                'latitude' => '22.71960000',
                'longitude' => '75.85770000',
                'created_at' => '2025-10-29 11:32:30',
                'updated_at' => '2025-10-29 23:59:41',
                'deleted_at' => NULL,
            ),
        ));
        
        
    }
}
