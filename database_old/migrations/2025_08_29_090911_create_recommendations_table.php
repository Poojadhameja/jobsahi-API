<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('recommended_by')->nullable();
            $table->dateTime('recommended_at')->nullable();
            $table->text('reason')->nullable();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::dropIfExists('recommendations');
    }
};
