<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('job_recommendations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('job_id');
            $table->enum('source', ['ai', 'trending', 'sponsored', 'manual'])->default('ai');
            $table->decimal('score', 5, 2)->default(0.00);
            $table->dateTime('created_at')->useCurrent();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('approved');

            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_recommendations');
    }
};
