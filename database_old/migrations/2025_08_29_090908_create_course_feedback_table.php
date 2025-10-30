<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('course_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->integer('rating')->default(0);
            $table->text('feedback')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('course_feedback');
    }
};
