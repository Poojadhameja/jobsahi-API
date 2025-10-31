<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('name', 100)->nullable();
            $table->string('batch_time_slot', 50)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('media')->nullable();
            $table->unsignedBigInteger('instructor_id');
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('approved');

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('instructor_id')->references('id')->on('faculty_users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('batches');
    }
};
