<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('skill_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('skill_tests', 'application_id')) {
                $table->unsignedBigInteger('application_id')->nullable()->after('student_id');
            }

            if (!Schema::hasColumn('skill_tests', 'job_id')) {
                $table->unsignedBigInteger('job_id')->nullable()->after('application_id');
            }

            if (Schema::hasColumn('skill_tests', 'application_id')) {
                $table->foreign('application_id')
                    ->references('id')
                    ->on('applications')
                    ->onDelete('cascade');
            }

            if (Schema::hasColumn('skill_tests', 'job_id')) {
                $table->foreign('job_id')
                    ->references('id')
                    ->on('jobs')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void {
        Schema::table('skill_tests', function (Blueprint $table) {
            if (Schema::hasColumn('skill_tests', 'job_id')) {
                $table->dropForeign(['job_id']);
                $table->dropColumn('job_id');
            }

            if (Schema::hasColumn('skill_tests', 'application_id')) {
                $table->dropForeign(['application_id']);
                $table->dropColumn('application_id');
            }
        });
    }
};

