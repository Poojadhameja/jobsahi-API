<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------
        // 🔹 1. Core & User Tables
        // -------------------------------
        $this->call([
            UsersTableSeeder::class,
            RecruiterProfilesTableSeeder::class,
            InstituteProfilesTableSeeder::class,
            FacultyUsersTableSeeder::class,
            StudentProfilesTableSeeder::class,
        ]);

        // -------------------------------
        // 🔹 2. Course-Related Tables
        // -------------------------------
        $this->call([
            CourseCategoryTableSeeder::class,
            CoursesTableSeeder::class,
            BatchesTableSeeder::class,
            StudentBatchesTableSeeder::class,
            CoursePaymentsTableSeeder::class,
            CourseFeedbackTableSeeder::class,
            StudentCourseEnrollmentsTableSeeder::class,
        ]);

        // -------------------------------
        // 🔹 3. Recruiter & Company Info
        // -------------------------------
        $this->call([
            RecruiterCompanyInfoTableSeeder::class,
        ]);

        // -------------------------------
        // 🔹 4. Job-Related Tables
        // -------------------------------
        $this->call([
            JobCategoryTableSeeder::class,
            JobsTableSeeder::class,
            JobFlagsTableSeeder::class,
            JobViewsTableSeeder::class,
            JobRecommendationsTableSeeder::class,
            ApplicationsTableSeeder::class,
            InterviewPanelTableSeeder::class,
            InterviewsTableSeeder::class,
        ]);

        // -------------------------------
        // 🔹 5. Certificates & Skill Tests
        // -------------------------------
        $this->call([
            CertificateTemplatesTableSeeder::class,
            CertificatesTableSeeder::class,
            SkillTestsTableSeeder::class,
            SkillQuestionsTableSeeder::class,
            SkillAttemptsTableSeeder::class,
        ]);

        // -------------------------------
        // 🔹 6. Communication & Notifications
        // -------------------------------
        $this->call([
            MessagesTableSeeder::class,
            MessageTemplatesTableSeeder::class,
            NotificationsTemplatesTableSeeder::class,
            NotificationsTableSeeder::class,
            OtpRequestsTableSeeder::class,
            SystemAlertsTableSeeder::class,
        ]);

        // -------------------------------
        // 🔹 7. Plans, Subscriptions & Transactions
        // -------------------------------
        $this->call([
            PlansTableSeeder::class,
            SubscriptionsTableSeeder::class,
            TransactionsTableSeeder::class,
        ]);

        // -------------------------------
        // 🔹 8. Reports, Logs & Referrals
        // -------------------------------
        $this->call([
            ReportsTableSeeder::class,
            ResumeAccessLogsTableSeeder::class,
            ReferralsTableSeeder::class,
            RecommendationsTableSeeder::class,
            ActivityLogsTableSeeder::class,
            BlacklistedTokensTableSeeder::class,
        ]);
    }
}
