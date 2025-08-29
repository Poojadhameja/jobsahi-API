<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->integer('rating'); // 1–5 ka validation Laravel side se karenge
            $table->text('feedback')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });

        // ✅ Agar DB level check chahiye, to raw SQL ka use karo:
        DB::statement('ALTER TABLE course_feedback ADD CONSTRAINT rating_check CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_feedback');
    }
};
