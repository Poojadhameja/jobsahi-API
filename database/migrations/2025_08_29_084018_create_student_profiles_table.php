<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('skills');
            $table->text('education');
            $table->string('resume');
            $table->string('portfolio_link');
            $table->string('linkedin_url');
            $table->date('dob');
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->enum('job_type', ['full_time', 'part_time', 'internship', 'contract'])->nullable();
            $table->string('trade', 100)->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_profiles');
    }
};