<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlansTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('plans')->delete();
        
        \DB::table('plans')->insert(array (
            0 => 
            array (
                'id' => 1,
                'title' => 'Basic Employer',
                'type' => 'employer',
                'price' => 5000,
                'duration_days' => 30,
                'features_json' => '{"job_posts": 5, "resume_views": 50, "priority_support": false}',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:06:24',
            ),
            1 => 
            array (
                'id' => 2,
                'title' => 'Premium Employer',
                'type' => 'employer',
                'price' => 12000,
                'duration_days' => 30,
                'features_json' => '{"job_posts": 20, "resume_views": 200, "priority_support": true, "featured_jobs": 3}',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:06:24',
            ),
            2 => 
            array (
                'id' => 3,
                'title' => 'Enterprise Employer',
                'type' => 'employer',
                'price' => 25000,
                'duration_days' => 30,
                'features_json' => '{"job_posts": "unlimited", "resume_views": "unlimited", "priority_support": true, "featured_jobs": 10, "dedicated_manager": true}',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:06:24',
            ),
            3 => 
            array (
                'id' => 4,
                'title' => 'Basic Institute',
                'type' => 'institute',
                'price' => 8000,
                'duration_days' => 30,
                'features_json' => '{"course_listings": 10, "student_enrollments": 100, "certificates": true}',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:06:24',
            ),
            4 => 
            array (
                'id' => 5,
                'title' => 'Premium Institute',
                'type' => 'institute',
                'price' => 15000,
                'duration_days' => 30,
                'features_json' => '{"course_listings": "unlimited", "student_enrollments": 500, "certificates": true, "analytics": true}',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:06:24',
            ),
            5 => 
            array (
                'id' => 6,
                'title' => 'Premium Plan',
                'type' => 'institute',
                'price' => 200,
                'duration_days' => 30,
                'features_json' => '["Unlimited access","Priority support","Free trials"]',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:06:24',
            ),
            6 => 
            array (
                'id' => 7,
                'title' => 'Premium Plan',
                'type' => 'employer',
                'price' => 200,
                'duration_days' => 30,
                'features_json' => '["Unlimited access","Priority support","Free trials"]',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:20:46',
            ),
            7 => 
            array (
                'id' => 8,
                'title' => 'Premium Plan',
                'type' => 'employer',
                'price' => 400,
                'duration_days' => 30,
                'features_json' => '["Unlimited access","Priority support","Free trials"]',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:23:21',
            ),
            8 => 
            array (
                'id' => 9,
                'title' => 'Premium Plan',
                'type' => 'employer',
                'price' => 1500,
                'duration_days' => 30,
                'features_json' => '["Unlimited access","Priority support","Free trials"]',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:23:37',
            ),
            9 => 
            array (
                'id' => 10,
                'title' => 'Premium Plan',
                'type' => 'institute',
                'price' => 13000,
                'duration_days' => 30,
                'features_json' => '["Unlimited access","Priority support","Free trials"]',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:24:06',
            ),
            10 => 
            array (
                'id' => 11,
                'title' => 'Premium Plan',
                'type' => 'institute',
                'price' => 200,
                'duration_days' => 60,
                'features_json' => '["Unlimited access","Priority support","Free trials"]',
                'created_at' => '2025-10-01 21:06:24',
                'modified_at' => '2025-10-01 21:06:24',
            ),
        ));
        
        
    }
}