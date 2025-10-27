<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
       Schema::create('student_course_enrollments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained('student_profiles')->onDelete('cascade');
    $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
    $table->date('enrollment_date')->nullable();
    $table->enum('status',['enrolled','completed','dropped'])->default('enrolled');
    $table->timestamps();
});

    }

    public function down(): void {
        Schema::dropIfExists('student_course_enrollments');
    }
};
