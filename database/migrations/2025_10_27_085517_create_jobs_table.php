<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1️⃣: Create table WITHOUT recruiter_company_info FK
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();

            // Recruiter relation (already exists)
            $table->foreignId('recruiter_id')
                ->constrained('recruiter_profiles')
                ->cascadeOnDelete();

            // Company Info ID (we'll add FK separately)
            $table->unsignedBigInteger('company_info_id')->nullable();

            // Category FK (safe)
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('job_category')
                ->nullOnDelete();

            // Job details
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('skills_required')->nullable();
            $table->decimal('salary_min', 10, 2)->default(0);
            $table->decimal('salary_max', 10, 2)->default(0);
            $table->enum('job_type', ['full_time', 'part_time', 'internship', 'contract'])->default('full_time');
            $table->string('location')->nullable();
            $table->integer('experience_required')->nullable();
            $table->dateTime('application_deadline')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->integer('no_of_vacancies')->nullable();
            $table->enum('status', ['open', 'closed', 'paused'])->default('open');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
        });

    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop table safely
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['company_info_id']);
        });
        Schema::dropIfExists('jobs');
    }
};
