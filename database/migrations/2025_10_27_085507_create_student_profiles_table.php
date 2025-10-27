<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('skills');
            $table->text('education');
            $table->string('resume', 255);
            $table->text('certificates')->nullable();
            $table->string('portfolio_link', 255)->nullable();
            $table->string('linkedin_url', 255);
            $table->date('dob');
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->enum('job_type', ['full_time', 'part_time', 'internship', 'contract'])->nullable();
            $table->string('trade', 100)->nullable();
            $table->string('location', 255)->nullable();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('student_profiles');
    }
};
