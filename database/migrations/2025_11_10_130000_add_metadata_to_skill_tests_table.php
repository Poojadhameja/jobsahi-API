<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('skill_tests', function (Blueprint $table) {
            if (!Schema::hasColumn('skill_tests', 'total_time_spent_seconds')) {
                $table->integer('total_time_spent_seconds')->nullable()->after('max_score');
            }
            if (!Schema::hasColumn('skill_tests', 'total_questions')) {
                $table->integer('total_questions')->nullable()->after('total_time_spent_seconds');
            }
            if (!Schema::hasColumn('skill_tests', 'attempted_questions')) {
                $table->integer('attempted_questions')->nullable()->after('total_questions');
            }
        });
    }

    public function down(): void {
        Schema::table('skill_tests', function (Blueprint $table) {
            if (Schema::hasColumn('skill_tests', 'attempted_questions')) {
                $table->dropColumn('attempted_questions');
            }
            if (Schema::hasColumn('skill_tests', 'total_questions')) {
                $table->dropColumn('total_questions');
            }
            if (Schema::hasColumn('skill_tests', 'total_time_spent_seconds')) {
                $table->dropColumn('total_time_spent_seconds');
            }
        });
    }
};

