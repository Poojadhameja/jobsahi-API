<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('job_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('job_id');
            $table->enum('source', ['ai', 'trending', 'sponsored', 'manual'])->default('ai');
            $table->decimal('score', 5, 2)->default(0.00);
            $table->dateTime('created_at')->useCurrent();
            
            $table->unique(['student_id', 'job_id'], 'uq_jrec');
            $table->index(['student_id', 'score'], 'idx_jrec_score');
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_recommendations');
    }
};