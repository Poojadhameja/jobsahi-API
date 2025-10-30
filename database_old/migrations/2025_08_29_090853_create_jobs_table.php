<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recruiter_id')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('location');
            $table->text('skills_required');
            $table->decimal('salary_min', 8, 2);
            $table->decimal('salary_max', 8, 2);
            $table->enum('job_type', ['full_time', 'part_time', 'internship', 'contract'])->default('full_time');
            $table->string('experience_required');
            $table->dateTime('application_deadline');
            $table->boolean('is_remote');
            $table->integer('no_of_vacancies');
            $table->enum('status', ['open', 'closed', 'paused'])->default('open');
            $table->dateTime('created_at');
            
            $table->foreign('recruiter_id')->references('id')->on('recruiter_profiles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('jobs');
    }
};