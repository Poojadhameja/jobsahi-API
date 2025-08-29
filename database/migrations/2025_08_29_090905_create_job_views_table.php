<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('job_views', function (Blueprint $table) {
            $table->id();
            $table->integer('job_id');
            $table->integer('student_id');
            $table->dateTime('viewed_at');
            
            $table->index('job_id', 'idx_jv_job_id');
            $table->index('student_id', 'idx_jv_student_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_views');
    }
};