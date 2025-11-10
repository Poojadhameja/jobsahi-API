<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();

            // ✅ Recruiter relation (main)
            $table->foreignId('recruiter_id')
                ->constrained('recruiter_profiles')
                ->cascadeOnDelete();

            // ✅ Company info (FK added later via separate migration)
            $table->unsignedBigInteger('company_info_id')->nullable();

            // ✅ Job category (safe FK)
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('job_category')
                ->nullOnDelete();

            // ✅ Job details
            $table->string('title', 255);
            $table->text('description');
            $table->string('location', 255);
            $table->text('skills_required');
            $table->decimal('salary_min', 8, 2);
            $table->decimal('salary_max', 8, 2);
            $table->enum('job_type', ['full_time', 'part_time', 'internship', 'contract'])->default('full_time');
            $table->string('experience_required', 255);
            $table->dateTime('application_deadline')->nullable();
            $table->tinyInteger('is_remote')->default(0);
            $table->integer('no_of_vacancies')->nullable();
            $table->enum('status', ['open', 'closed', 'paused'])->default('open');
            $table->tinyInteger('is_featured')->default(0);
            $table->tinyInteger('save_status')->default(0);
            $table->unsignedBigInteger('saved_by_student_id')->nullable();

            // ✅ Admin action + timestamps
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            // ✅ Foreign keys except company_info_id
            $table->foreign('saved_by_student_id')
                ->references('id')
                ->on('student_profiles')
                ->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::dropIfExists('jobs');
    }
};
