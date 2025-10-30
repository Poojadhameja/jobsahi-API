<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('job_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('flagged_by');
            $table->text('reason')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
            $table->foreign('flagged_by')->references('id')->on('student_profiles')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_flags');
    }
};
