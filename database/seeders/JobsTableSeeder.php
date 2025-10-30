<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JobsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('jobs')->delete();
        
        \DB::table('jobs')->insert(array (
            0 => 
            array (
                'id' => 1,
                'recruiter_id' => 1,
                'company_info_id' => 2,
                'category_id' => 2,
                'title' => 'Electrical Supervisor',
                'description' => 'Manage junior electricians on-site.',
                'location' => 'Indore',
                'skills_required' => 'Maintenance, Circuit Design',
                'salary_min' => '15000.00',
                'salary_max' => '25000.00',
                'job_type' => 'full_time',
                'experience_required' => '1-3 years',
                'application_deadline' => '2025-12-01 00:00:00',
                'is_remote' => 0,
                'no_of_vacancies' => 2,
                'status' => 'open',
                'is_featured' => 0,
                'save_status' => 0,
                'saved_by_student_id' => NULL,
                'admin_action' => 'approved',
                'created_at' => '2025-10-30 02:08:19',
                'updated_at' => '2025-10-30 02:25:49',
            ),
            1 => 
            array (
                'id' => 2,
                'recruiter_id' => 1,
                'company_info_id' => 1,
                'category_id' => 1,
                'title' => 'Civil Site Engineer',
                'description' => 'Supervise on-site construction and safety measures.',
                'location' => 'Indore',
                'skills_required' => 'AutoCAD, Site Management',
                'salary_min' => '18000.00',
                'salary_max' => '25000.00',
                'job_type' => 'full_time',
                'experience_required' => '1-3 years',
                'application_deadline' => '2025-11-30 00:00:00',
                'is_remote' => 0,
                'no_of_vacancies' => 2,
                'status' => 'open',
                'is_featured' => 0,
                'save_status' => 0,
                'saved_by_student_id' => NULL,
                'admin_action' => 'approved',
                'created_at' => '2025-10-30 02:18:28',
                'updated_at' => '2025-10-30 02:25:53',
            ),
        ));
        
        
    }
}