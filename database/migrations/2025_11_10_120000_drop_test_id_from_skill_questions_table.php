<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('skill_questions') && Schema::hasColumn('skill_questions', 'test_id')) {
            Schema::table('skill_questions', function (Blueprint $table) {
                // Drop the foreign key constraint if present before removing the column.
                $table->dropForeign(['test_id']);
            });

            Schema::table('skill_questions', function (Blueprint $table) {
                $table->dropColumn('test_id');
            });
        }
    }

    public function down(): void {
        if (Schema::hasTable('skill_questions') && !Schema::hasColumn('skill_questions', 'test_id')) {
            Schema::table('skill_questions', function (Blueprint $table) {
                $table->unsignedBigInteger('test_id')->nullable()->after('job_id');
            });

            Schema::table('skill_questions', function (Blueprint $table) {
                $table->foreign('test_id')
                    ->references('id')
                    ->on('skill_tests')
                    ->onDelete('cascade');
            });
        }
    }
};

