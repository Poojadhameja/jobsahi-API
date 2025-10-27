<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('certificates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained('student_profiles')->onDelete('cascade');
    $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
    $table->string('certificate_no')->unique();
    $table->string('file_path')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
