<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            
            // ✅ Foreign Key
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // ✅ Basic Details
            $table->text('skills')->nullable();
            $table->text('education')->nullable();
            $table->string('resume', 255)->nullable();
            $table->text('certificates')->nullable();
            $table->string('portfolio_link', 255)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();

            // ✅ Job Preference
            $table->enum('job_type', ['full_time', 'part_time', 'internship', 'contract'])->nullable();
            $table->string('trade', 100)->nullable();
            $table->string('location', 255)->nullable();

            // ✅ Extra Info (from phpMyAdmin)
            $table->text('experience')->nullable();
            $table->string('projects', 255)->nullable();
            $table->string('languages', 255)->nullable();
            $table->string('aadhar_number', 20)->nullable()->unique();
            $table->integer('graduation_year')->nullable();
            $table->decimal('cgpa', 3, 2)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // ✅ System Fields
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('student_profiles');
    }
};
