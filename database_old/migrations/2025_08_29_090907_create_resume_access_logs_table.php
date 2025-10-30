<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('resume_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recruiter_id');
            $table->unsignedBigInteger('student_id');
            $table->dateTime('viewed_at')->useCurrent();

            // Foreign keys
            $table->foreign('recruiter_id')->references('id')->on('recruiter_profiles')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('resume_access_logs');
    }
};
