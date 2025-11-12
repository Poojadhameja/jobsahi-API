<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ApplicationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('applications')->delete();
        
        \DB::table('applications')->insert(array (
            0 => 
            array (
                'id' => 1,
                'job_id' => 1,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 1,
                'status' => 'shortlisted',
                'applied_at' => '2024-08-10 10:00:00',
                'resume_link' => '/uploads/resumes/rahul_kumar_resume.pdf',
                'cover_letter' => 'I am very interested in this position and believe my Java and Spring Boot experience makes me a great fit.',
                'created_at' => '2024-08-10 10:00:00',
                'modified_at' => '2025-09-02 15:57:10',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            1 => 
            array (
                'id' => 2,
                'job_id' => 2,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 4,
                'status' => 'shortlisted',
                'applied_at' => '2024-08-11 11:00:00',
                'resume_link' => '/uploads/resumes/kavya_patel_resume.pdf',
                'cover_letter' => 'I am passionate about frontend development and have strong React skills.',
                'created_at' => '2024-08-11 11:00:00',
                'modified_at' => '2025-09-01 16:21:34',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            2 => 
            array (
                'id' => 4,
                'job_id' => 4,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 3,
                'status' => '',
                'applied_at' => '2024-08-12 13:00:00',
                'resume_link' => '/uploads/resumes/amit_sharma_resume.pdf',
                'cover_letter' => 'My experience with AWS and Docker aligns perfectly with your DevOps requirements.',
                'created_at' => '2024-08-12 13:00:00',
                'modified_at' => '2025-09-03 22:54:52',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            3 => 
            array (
                'id' => 5,
                'job_id' => 5,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 5,
                'status' => 'shortlisted',
                'applied_at' => '2024-08-09 14:00:00',
                'resume_link' => '/uploads/resumes/arjun_reddy_resume.pdf',
                'cover_letter' => 'I have strong Android development skills and experience with Kotlin.',
                'created_at' => '2024-08-09 14:00:00',
                'modified_at' => '2025-08-26 13:35:24',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            4 => 
            array (
                'id' => 6,
                'job_id' => 6,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 6,
                'status' => 'applied',
                'applied_at' => '2024-08-13 15:00:00',
                'resume_link' => '/uploads/resumes/neha_gupta_resume.pdf',
                'cover_letter' => 'My MBA in Marketing and digital marketing experience make me ideal for this position.',
                'created_at' => '2024-08-13 15:00:00',
                'modified_at' => '2025-08-26 13:35:24',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            5 => 
            array (
                'id' => 7,
                'job_id' => 7,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 1,
                'status' => 'applied',
                'applied_at' => '2024-08-14 16:00:00',
                'resume_link' => '/uploads/resumes/rahul_kumar_resume.pdf',
                'cover_letter' => 'Looking forward to gaining industry experience through this internship.',
                'created_at' => '2024-08-14 16:00:00',
                'modified_at' => '2025-08-26 13:35:24',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            6 => 
            array (
                'id' => 8,
                'job_id' => 7,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 3,
                'status' => 'applied',
                'applied_at' => '2024-08-14 17:00:00',
                'resume_link' => '/uploads/resumes/amit_sharma_resume.pdf',
                'cover_letter' => 'This internship aligns perfectly with my career goals.',
                'created_at' => '2024-08-14 17:00:00',
                'modified_at' => '2025-08-26 13:35:24',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            7 => 
            array (
                'id' => 9,
                'job_id' => 6,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 4,
                'status' => 'rejected',
                'applied_at' => '2025-08-27 16:56:09',
                'resume_link' => '/uploads/resumes/rahul_kumar_resume.pdf',
                'cover_letter' => 'Looking forward to gaining industry experience.',
                'created_at' => '2025-08-27 16:56:09',
                'modified_at' => '2025-08-31 19:57:30',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            8 => 
            array (
                'id' => 10,
                'job_id' => 4,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 4,
                'status' => 'shortlisted',
                'applied_at' => '2025-08-27 16:56:18',
                'resume_link' => '/uploads/resumes/rahul_kumar_resume.pdf',
                'cover_letter' => 'Looking forward to gaining industry experience.',
                'created_at' => '2025-08-27 16:56:18',
                'modified_at' => '2025-08-31 19:57:38',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            9 => 
            array (
                'id' => 12,
                'job_id' => 3,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 3,
                'status' => 'selected',
                'applied_at' => '2025-08-27 16:56:51',
                'resume_link' => '/uploads/resumes/rahul_kumar_resume.pdf',
                'cover_letter' => 'Looking forward to gaining industry experience.',
                'created_at' => '2025-08-27 16:56:51',
                'modified_at' => '2025-08-31 19:57:46',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            10 => 
            array (
                'id' => 13,
                'job_id' => 3,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 5,
                'status' => 'applied',
                'applied_at' => '2025-08-27 17:06:14',
                'resume_link' => '/uploads/resumes/rahul_kumar_resume.pdf',
                'cover_letter' => 'Looking forward to gaining industry experience.',
                'created_at' => '2025-08-27 17:06:14',
                'modified_at' => '2025-08-31 19:57:55',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            11 => 
            array (
                'id' => 20,
                'job_id' => 7,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 5,
                'status' => 'rejected',
                'applied_at' => '2025-08-31 19:55:31',
                'resume_link' => 'http://example.com/resume.pdf',
                'cover_letter' => 'I am really interested in this role.',
                'created_at' => '2025-08-31 19:55:31',
                'modified_at' => '2025-08-31 19:58:02',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            12 => 
            array (
                'id' => 21,
                'job_id' => 6,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 5,
                'status' => 'applied',
                'applied_at' => '2025-08-31 19:56:58',
                'resume_link' => 'http://example.com/resume.pdf',
                'cover_letter' => 'I am really interested in this role.',
                'created_at' => '2025-08-31 19:56:58',
                'modified_at' => '2025-08-31 19:57:22',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            13 => 
            array (
                'id' => 22,
                'job_id' => 3,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 6,
                'status' => '',
                'applied_at' => '2025-09-04 09:49:26',
                'resume_link' => 'https://example.com/my_resume.pdf',
                'cover_letter' => 'I am very interested in this position because...',
                'created_at' => '2025-09-04 09:49:26',
                'modified_at' => '2025-09-04 09:49:26',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            14 => 
            array (
                'id' => 23,
                'job_id' => 4,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 6,
                'status' => '',
                'applied_at' => '2025-09-04 10:08:04',
                'resume_link' => 'https://example.com/my_resume.pdf',
                'cover_letter' => 'I am very interested in this position because...',
                'created_at' => '2025-09-04 10:08:04',
                'modified_at' => '2025-09-04 10:08:04',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            15 => 
            array (
                'id' => 24,
                'job_id' => 4,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 5,
                'status' => '',
                'applied_at' => '2025-09-04 10:08:30',
                'resume_link' => 'https://example.com/my_resume.pdf',
                'cover_letter' => 'I am very interested in this position because...',
                'created_at' => '2025-09-04 10:08:30',
                'modified_at' => '2025-09-04 10:08:30',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
            16 => 
            array (
                'id' => 25,
                'job_id' => 9,
                'interview_id' => NULL,
                'job_selected' => 0,
                'student_id' => 6,
                'status' => '',
                'applied_at' => '2025-09-04 10:09:43',
                'resume_link' => 'https://example.com/my_resume.pdf',
                'cover_letter' => 'I am very interested in this position because...',
                'created_at' => '2025-09-04 10:09:43',
                'modified_at' => '2025-09-04 10:09:43',
                'deleted_at' => NULL,
                'admin_action' => 'pending',
            ),
        ));
        
        
    }
}