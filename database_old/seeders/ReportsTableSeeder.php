<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReportsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('reports')->delete();
        
        \DB::table('reports')->insert(array (
            0 => 
            array (
                'id' => 2,
                'generated_by' => 6,
                'report_type' => 'revenue_report',
                'filters_applied' => '{"status":"approved","date_range":"2025-09-01"}',
                'download_url' => 'http://localhost/reports/downloads/applicants_report.pdf',
                'generated_at' => '2025-09-04 13:38:18',
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 3,
                'generated_by' => 6,
                'report_type' => 'job_summary',
                'filters_applied' => '{"course":"BCA","year":"2025"}',
                'download_url' => 'http://localhost/reports/report123.pdf',
                'generated_at' => '2025-09-04 13:44:26',
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 4,
                'generated_by' => 6,
                'report_type' => 'placement_funnel',
                'filters_applied' => '{"course":"BCA","year":"2025"}',
                'download_url' => 'http://localhost/reports/report123.pdf',
                'generated_at' => '2025-09-04 13:47:03',
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 5,
                'generated_by' => 6,
                'report_type' => 'placement_funnel',
                'filters_applied' => '{"course":"BCA","year":"2025"}',
                'download_url' => 'http://localhost/reports/report123.pdf',
                'generated_at' => '2025-09-04 13:48:34',
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 6,
                'generated_by' => 20,
                'report_type' => 'revenue_report',
                'filters_applied' => '{"course":"BCA","year":"2025"}',
                'download_url' => 'http://localhost/reports/report123.pdf',
                'generated_at' => '2025-09-04 13:50:46',
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 7,
                'generated_by' => 48,
                'report_type' => 'job_summary',
                'filters_applied' => '{"course_id":5,"semester":"Fall 2025"}',
                'download_url' => 'http://localhost/reports/student_progress_5.pdf',
                'generated_at' => '2025-09-27 18:02:56',
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 8,
                'generated_by' => 48,
                'report_type' => 'job_summary',
                'filters_applied' => '{"course_id":5,"semester":"Fall 2025"}',
                'download_url' => 'http://localhost/reports/student_progress_5.pdf',
                'generated_at' => '2025-09-27 18:04:08',
                'admin_action' => 'pending',
            ),
            7 => 
            array (
                'id' => 9,
                'generated_by' => 48,
                'report_type' => 'placement_funnel',
                'filters_applied' => '{"course_id":5,"semester":"Fall 2025"}',
                'download_url' => 'http://localhost/reports/student_progress_5.pdf',
                'generated_at' => '2025-09-27 18:07:36',
                'admin_action' => 'pending',
            ),
            8 => 
            array (
                'id' => 10,
                'generated_by' => 48,
                'report_type' => 'revenue_report',
                'filters_applied' => '{"course_id":5,"semester":"Fall 2025"}',
                'download_url' => 'http://localhost/reports/student_progress_5.pdf',
                'generated_at' => '2025-09-27 18:09:09',
                'admin_action' => 'pending',
            ),
            9 => 
            array (
                'id' => 11,
                'generated_by' => 48,
                'report_type' => 'job_summary',
                'filters_applied' => '{"course_id":5,"semester":"Fall 2025"}',
                'download_url' => 'http://localhost/reports/student_progress_5.pdf',
                'generated_at' => '2025-09-27 18:09:34',
                'admin_action' => 'pending',
            ),
            10 => 
            array (
                'id' => 12,
                'generated_by' => 48,
                'report_type' => 'revenue_report',
                'filters_applied' => '{"course":"BCA","year":"2025"}',
                'download_url' => 'http://localhost/reports/report123.pdf',
                'generated_at' => '2025-09-27 18:11:56',
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}