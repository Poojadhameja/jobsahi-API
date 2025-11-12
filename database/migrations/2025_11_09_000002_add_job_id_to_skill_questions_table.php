<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('skill_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('skill_questions', 'job_id')) {
                $table->unsignedBigInteger('job_id')->nullable()->after('id')->index();
            }
        });

        if (Schema::hasColumn('skill_questions', 'job_id')) {
            Schema::table('skill_questions', function (Blueprint $table) {
                $table->foreign('job_id')
                    ->references('id')
                    ->on('jobs')
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('skill_questions', 'test_id') && Schema::hasColumn('skill_tests', 'job_id')) {
            DB::statement("
                UPDATE skill_questions sq
                JOIN skill_tests st ON sq.test_id = st.id
                SET sq.job_id = st.job_id
                WHERE sq.job_id IS NULL AND st.job_id IS NOT NULL
            ");
        }
    }

    public function down(): void {
        Schema::table('skill_questions', function (Blueprint $table) {
            if (Schema::hasColumn('skill_questions', 'job_id')) {
                $table->dropForeign(['job_id']);
                $table->dropColumn('job_id');
            }
        });
    }
};

