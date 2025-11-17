<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Check if table exists before proceeding
        if (!Schema::hasTable('student_profiles')) {
            return;
        }
        
        // Step 1: Add socials column if it doesn't exist
        Schema::table('student_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('student_profiles', 'socials')) {
                DB::statement('ALTER TABLE student_profiles ADD COLUMN socials TEXT NULL AFTER certificates');
            }
        });
        
        // Step 2: Migrate existing portfolio_link and linkedin_url to socials JSON
        // This will be done via raw SQL to preserve existing data
        $records = DB::select("SELECT id, portfolio_link, linkedin_url FROM student_profiles WHERE (portfolio_link IS NOT NULL AND portfolio_link != '') OR (linkedin_url IS NOT NULL AND linkedin_url != '')");
        
        foreach ($records as $record) {
            $socials = [];
            
            if (!empty($record->portfolio_link)) {
                $socials[] = [
                    "title" => "Portfolio",
                    "profile_url" => $record->portfolio_link
                ];
            }
            
            if (!empty($record->linkedin_url)) {
                $socials[] = [
                    "title" => "LinkedIn",
                    "profile_url" => $record->linkedin_url
                ];
            }
            
            if (!empty($socials)) {
                $socialsJson = json_encode($socials, JSON_UNESCAPED_UNICODE);
                DB::statement("UPDATE student_profiles SET socials = ? WHERE id = ?", [$socialsJson, $record->id]);
            }
        }
        
        // Step 3: Drop portfolio_link and linkedin_url columns
        Schema::table('student_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('student_profiles', 'portfolio_link')) {
                $table->dropColumn('portfolio_link');
            }
            if (Schema::hasColumn('student_profiles', 'linkedin_url')) {
                $table->dropColumn('linkedin_url');
            }
        });
    }

    public function down(): void {
        // Check if table exists before proceeding
        if (!Schema::hasTable('student_profiles')) {
            return;
        }
        
        Schema::table('student_profiles', function (Blueprint $table) {
            // Re-add portfolio_link and linkedin_url columns
            if (!Schema::hasColumn('student_profiles', 'portfolio_link')) {
                DB::statement('ALTER TABLE student_profiles ADD COLUMN portfolio_link VARCHAR(255) NULL AFTER certificates');
            }
            if (!Schema::hasColumn('student_profiles', 'linkedin_url')) {
                DB::statement('ALTER TABLE student_profiles ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER portfolio_link');
            }
        });
        
        // Migrate socials back to portfolio_link and linkedin_url
        $records = DB::select("SELECT id, socials FROM student_profiles WHERE socials IS NOT NULL AND socials != ''");
        
        foreach ($records as $record) {
            $socials = json_decode($record->socials, true);
            if (is_array($socials)) {
                $portfolio_link = null;
                $linkedin_url = null;
                
                foreach ($socials as $social) {
                    $title = strtolower($social['title'] ?? '');
                    if ($title === 'portfolio') {
                        $portfolio_link = $social['profile_url'] ?? null;
                    } elseif ($title === 'linkedin') {
                        $linkedin_url = $social['profile_url'] ?? null;
                    }
                }
                
                if ($portfolio_link || $linkedin_url) {
                    DB::statement("UPDATE student_profiles SET portfolio_link = ?, linkedin_url = ? WHERE id = ?", [
                        $portfolio_link ?? null,
                        $linkedin_url ?? null,
                        $record->id
                    ]);
                }
            }
        }
        
        // Drop socials column
        Schema::table('student_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('student_profiles', 'socials')) {
                $table->dropColumn('socials');
            }
        });
    }
};

