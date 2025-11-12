<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('saved_courses', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');

            // Timestamps
            $table->dateTime('saved_at')->useCurrent();

            // Constraints
            $table->foreign('student_id')
                ->references('id')
                ->on('student_profiles')
                ->onDelete('cascade');

            $table->foreign('course_id')
                ->references('id')
                ->on('courses')
                ->onDelete('cascade');

            // Indexes
            $table->index('student_id');
            $table->index('course_id');
            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('saved_courses');
    }
};


