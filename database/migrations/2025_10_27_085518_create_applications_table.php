<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // ✅ Step 1: Create table without risky FK dependency
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            // ✅ Safe Foreign Keys
            $table->foreignId('job_id')
                ->constrained('jobs')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('interview_id')->nullable(); // add FK later safely

            $table->foreignId('student_id')
                ->constrained('student_profiles')
                ->cascadeOnDelete();

            // ✅ Main Columns
            $table->dateTime('applied_at')->nullable();
            $table->string('resume_link', 255)->nullable();
            $table->text('cover_letter')->nullable();
            $table->enum('status', ['applied', 'shortlisted', 'rejected', 'selected'])->default('applied');

            // ✅ Admin & Timestamps
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });

        // ✅ Step 2: Add interview FK safely after interviews table exists
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasTable('interviews')) {
                $table->foreign('interview_id')
                      ->references('id')
                      ->on('interviews')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['interview_id']);
        });

        Schema::dropIfExists('applications');
    }
};
