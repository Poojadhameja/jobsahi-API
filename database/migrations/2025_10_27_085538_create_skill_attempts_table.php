<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('skill_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('test_id');
            $table->unsignedBigInteger('question_id');
            $table->enum('selected_option', ['A', 'B', 'C', 'D'])->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('attempt_number')->default(1);
            $table->integer('time_taken_seconds')->default(0);
            $table->dateTime('answered_at')->nullable();

            // Foreign key relations
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('test_id')->references('id')->on('skill_tests')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('skill_questions')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('skill_attempts');
    }
};
