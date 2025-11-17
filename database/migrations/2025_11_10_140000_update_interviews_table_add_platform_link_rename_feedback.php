<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('interviews', function (Blueprint $table) {
            // Add platform_name column if it doesn't exist
            if (!Schema::hasColumn('interviews', 'platform_name')) {
                DB::statement('ALTER TABLE interviews ADD COLUMN platform_name VARCHAR(100) NULL AFTER mode');
            }
            
            // Add interview_link column if it doesn't exist
            if (!Schema::hasColumn('interviews', 'interview_link')) {
                DB::statement('ALTER TABLE interviews ADD COLUMN interview_link TEXT NULL AFTER platform_name');
            }
            
            // Rename feedback to interview_info if feedback exists and interview_info doesn't
            if (Schema::hasColumn('interviews', 'feedback') && !Schema::hasColumn('interviews', 'interview_info')) {
                // Use raw SQL for renaming as renameColumn requires doctrine/dbal
                DB::statement('ALTER TABLE interviews CHANGE feedback interview_info TEXT NULL');
            } elseif (!Schema::hasColumn('interviews', 'interview_info')) {
                // If neither exists, create interview_info
                DB::statement('ALTER TABLE interviews ADD COLUMN interview_info TEXT NULL AFTER status');
            }
        });
    }

    public function down(): void {
        Schema::table('interviews', function (Blueprint $table) {
            if (Schema::hasColumn('interviews', 'interview_link')) {
                $table->dropColumn('interview_link');
            }
            if (Schema::hasColumn('interviews', 'platform_name')) {
                $table->dropColumn('platform_name');
            }
            if (Schema::hasColumn('interviews', 'interview_info') && !Schema::hasColumn('interviews', 'feedback')) {
                // Use raw SQL for renaming as renameColumn requires doctrine/dbal
                DB::statement('ALTER TABLE interviews CHANGE interview_info feedback TEXT NULL');
            } elseif (Schema::hasColumn('interviews', 'interview_info')) {
                $table->dropColumn('interview_info');
            }
        });
    }
};
