<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employer_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recruiter_id');
            $table->unsignedBigInteger('job_id')->nullable();
            $table->integer('rating')->default(0);
            $table->text('feedback')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('recruiter_id', 'fk_feedback_recruiter');
            $table->index('job_id', 'fk_feedback_job');
            
            $table->foreign('recruiter_id', 'fk_feedback_recruiter')
                ->references('id')
                ->on('recruiter_profiles')
                ->onDelete('cascade');
            
            $table->foreign('job_id', 'fk_feedback_job')
                ->references('id')
                ->on('jobs')
                ->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::table('employer_feedback', function (Blueprint $table) {
            $table->dropForeign('fk_feedback_job');
            $table->dropForeign('fk_feedback_recruiter');
        });
        Schema::dropIfExists('employer_feedback');
    }
};

